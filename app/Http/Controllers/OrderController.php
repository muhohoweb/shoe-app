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
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
            'payment_status' => 'required|in:pending,paid,failed',
            'tracking_number' => 'nullable|string|max:255',
        ]);

        $order->update($validated);

        return back();
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return back();
    }
}