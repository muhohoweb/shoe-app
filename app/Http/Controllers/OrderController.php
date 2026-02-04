<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
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
        $order->update([
            'status' => $request->status,
            'payment_status' => $request->payment_status,
            'tracking_number' => $request->tracking_number,
        ]);

        // Send WhatsApp dispatch notification
        if ($request->send_dispatch && $request->status === 'completed') {
            $whatsapp = new WhatsAppController();
            $whatsapp->sendDispatchNotification(
                new \Illuminate\Http\Request([
                    'to' => $order->mpesa_number,
                    'name' => $order->customer_name ?? 'Customer',
                    'order_id' => $order->uuid,
                    'destination' => $order->town,
                    'delivery_date' => now()->addDays(2)->format('M d, Y'),
                ])
            );
        }

        return back();
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return back();
    }
}