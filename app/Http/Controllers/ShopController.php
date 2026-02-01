<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
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

        $categories = \App\Models\Category::withCount([
            'products' => fn($q) => $q->where('is_active', true)->where('status', 'active')->where('stock', '>', 0)
        ])->get();

        return Inertia::render('Welcome', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'mpesa_number' => 'required|string|min:10|max:15',
            'town' => 'required|string|max:255',
            'description' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.size' => 'required|string',
            'items.*.color' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        // Calculate total from server-side product prices to prevent tampering
        $total = 0;
        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $total += $product->price * $item['quantity'];
        }

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'customer_name' => $validated['customer_name'],
            'mpesa_number' => $validated['mpesa_number'],
            'amount' => $total,
            'town' => $validated['town'],
            'description' => $validated['description'],
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $order->items()->create([
                'product_id' => $item['product_id'],
                'size' => $item['size'],
                'color' => $item['color'],
                'price' => $product->price,
                'quantity' => $item['quantity'],
            ]);

            // Decrement stock
            $product->decrement('stock', $item['quantity']);
        }

        return back()->with('orderSuccess', [
            'uuid' => $order->uuid,
            'amount' => $total,
        ]);
    }
}