<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        return Inertia::render('orders/Index', [
            'orders' => Order::query()
                ->with(['items.product.images'])
                ->latest()
                ->paginate(15),
            'products' => Product::where('is_active', true)
                ->where('status', 'active')
                ->with('images')
                ->select('id', 'name', 'price', 'colors', 'sizes')
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        'colors' => $product->colors,
                        'sizes' => $product->sizes,
                        'image_path' => $product->images->first()?->path,
                    ];
                }),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'mpesa_number' => 'required|string|max:255',
            'mpesa_code' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'payment_status' => 'required|in:pending,paid,failed',
            'tracking_number' => 'nullable|string',
            'town' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'required|in:pending,processing,completed,cancelled',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.size' => 'nullable|string',
            'items.*.color' => 'nullable|string',
        ]);

        $order = Order::query()->create([
            'uuid' => Str::uuid(),
            'customer_name' => $validated['customer_name'],
            'mpesa_number' => $validated['mpesa_number'],
            'mpesa_code' => $validated['mpesa_code'],
            'amount' => $validated['amount'],
            'payment_status' => $validated['payment_status'],
            'tracking_number' => $validated['tracking_number'],
            'town' => $validated['town'],
            'description' => $validated['description'],
            'status' => $validated['status'],
        ]);

        foreach ($validated['items'] as $item) {
            $order->items()->create($item);
        }

        return back();
    }

    public function update(Request $request, Order $order)
    {
        Log::info('Order update request', $request->all());

        $order->update([
            'status' => $request->status,
            'payment_status' => $request->payment_status,
            'tracking_number' => $request->tracking_number,
        ]);

        if ($request->send_dispatch && $request->status === 'completed') {
            Log::info('Sending WhatsApp dispatch', [
                'to' => $order->mpesa_number,
                'name' => $order->customer_name,
            ]);

            try {
                $whatsApp = new WhatsAppController();
                $whatsApp->sendWhatsAppMessage(new Request([
                    'phone' => preg_replace('/^0/', '254', $order->mpesa_number),
                    'message' => "Hi {$order->customer_name}, your order {$order->uuid} has been dispatched to {$order->town} and is expected by " . now()->addDays(2)->format('M d, Y') . ".",
                ]));
            } catch (\Exception $e) {
                Log::error('WhatsApp error', ['error' => $e->getMessage()]);
            }
        }

        return back();
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return back();
    }
}