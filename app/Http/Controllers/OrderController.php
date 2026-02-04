<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        return Inertia::render('orders/Index', [
            'orders' => Order::query()
                ->with('items.product')
                ->latest()
                ->paginate(15),
        ]);
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
                $response = Http::withToken(env('WHATSAPP_ACCESS_TOKEN'))
                    ->post('https://graph.facebook.com/v21.0/' . env('WHATSAPP_PHONE_NUMBER_ID') . '/messages', [
                        'messaging_product' => 'whatsapp',
                        'to' => $order->mpesa_number,
                        'type' => 'template',
                        'template' => [
                            'name' => 'dispatch',
                            'language' => ['code' => 'en'],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $order->customer_name ?? 'Customer'],
                                        ['type' => 'text', 'text' => $order->uuid],
                                        ['type' => 'text', 'text' => $order->town],
                                        ['type' => 'text', 'text' => now()->addDays(2)->format('M d, Y')],
                                    ]
                                ]
                            ]
                        ]
                    ]);

                Log::info('WhatsApp response', $response->json());
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