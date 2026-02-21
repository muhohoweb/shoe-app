<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DeliveryLocation;
use App\Models\MpesaTransaction;
use App\Models\Order;
use App\Models\Product;
use Iankumu\Mpesa\Facades\Mpesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ShopController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'images')
            ->where('is_active', true)
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->latest()
            ->get();

        $categories = Category::query()->withCount([
            'products' => fn($q) => $q->where('is_active', true)->where('status', 'active')->where('stock', '>', 0)
        ])->get();

        $locations = DeliveryLocation::active()
            ->get(['id', 'town', 'delivery_fee']);

        return Inertia::render('Welcome', [
            'products'   => $products,
            'categories' => $categories,
            'locations'  => $locations,
        ]);
    }

    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'customer_name'       => 'required|string|max:255',
            'mpesa_number'        => 'required|string|min:10|max:15',
            'delivery_location_id'=> 'required|integer|exists:delivery_locations,id',
            'description'         => 'required|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer|exists:products,id',
            'items.*.size'        => 'required|string',
            'items.*.color'       => 'required|string',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.price'       => 'required|numeric|min:0',
        ]);

        // Resolve delivery location
        $location = DeliveryLocation::findOrFail($validated['delivery_location_id']);

        // Calculate items total from server-side prices
        $itemsTotal = 0;
        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $itemsTotal += $product->price * $item['quantity'];
        }

        $deliveryFee = $location->delivery_fee;
        $grandTotal  = $itemsTotal + $deliveryFee;

        $trackingNumber = strtoupper(Str::random(6));

        // Create order
        $order = Order::query()->create([
            'uuid'          => (string) Str::uuid(),
            'customer_name' => $validated['customer_name'],
            'mpesa_number'  => $validated['mpesa_number'],
            'tracking_number'=> $trackingNumber,
            'amount'        => $grandTotal,
            'delivery_fee'  => $deliveryFee,
            'town'          => $location->town,
            'description'   => $validated['description'],
            'status'        => 'pending',
            'payment_status'=> 'pending',
        ]);

        // Create order items
        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $order->items()->create([
                'product_id' => $item['product_id'],
                'size'       => $item['size'],
                'color'      => $item['color'],
                'price'      => $product->price,
                'quantity'   => $item['quantity'],
            ]);

            $product->decrement('stock', $item['quantity']);
        }

        // Initiate M-Pesa STK Push with grand total
        $stkResult = $this->initiateStkPush($order, $validated['mpesa_number'], (int) $grandTotal);

        return back()->with('orderSuccess', [
            'uuid'               => $order->uuid,
            'amount'             => $grandTotal,
            'delivery_fee'       => $deliveryFee,
            'town'               => $location->town,
            'stk_sent'           => $stkResult['success'],
            'stk_message'        => $stkResult['message'],
            'checkout_request_id'=> $stkResult['checkout_request_id'] ?? null,
        ]);
    }

    private function initiateStkPush(Order $order, string $phoneNumber, int $amount): array
    {
        $phone = preg_replace('/^0/', '254', $phoneNumber);
        $phone = preg_replace('/^\+/', '', $phone);

        try {
            $response = Mpesa::stkpush(
                phonenumber: $phone,
                amount: $amount,
                account_number: $order->tracking_number,
                callbackurl: config('mpesa.callbacks.callback_url'),
                transactionType: 'CustomerPayBillOnline'
            );

            $result = $response->json();

            Log::info('M-Pesa STK Push Response', $result);

            if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                MpesaTransaction::create([
                    'order_id'           => $order->id,
                    'merchant_request_id'=> $result['MerchantRequestID'],
                    'checkout_request_id'=> $result['CheckoutRequestID'],
                    'phone_number'       => $phone,
                    'amount'             => $amount,
                    'account_reference'  => $order->tracking_number,
                    'status'             => 'pending',
                ]);

                return [
                    'success'             => true,
                    'message'             => 'Check your phone for M-Pesa prompt',
                    'checkout_request_id' => $result['CheckoutRequestID'],
                ];
            }

            return [
                'success' => false,
                'message' => $result['ResponseDescription'] ?? 'Failed to send M-Pesa prompt',
            ];

        } catch (\Exception $e) {
            Log::error('M-Pesa STK Push Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Payment service temporarily unavailable',
            ];
        }
    }

    public function settings()
    {
        $stats = [
            'total_transactions' => MpesaTransaction::count(),
            'completed'          => MpesaTransaction::where('status', 'completed')->count(),
            'pending'            => MpesaTransaction::where('status', 'pending')->count(),
            'failed'             => MpesaTransaction::where('status', 'failed')->count(),
            'total_amount'       => MpesaTransaction::where('status', 'completed')->sum('amount'),
        ];

        return Inertia::render('settings/Mpesa', [
            'stats' => $stats,
        ]);
    }
}