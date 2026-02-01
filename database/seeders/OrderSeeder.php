<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Item;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Please seed products first.');
            return;
        }

        $towns = ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika', 'Nyeri', 'Machakos'];
        $statuses = ['pending', 'processing', 'completed', 'cancelled'];
        $paymentStatuses = ['pending', 'paid', 'failed'];

        for ($i = 0; $i < 20; $i++) {
            $order = Order::create([
                'uuid' => Str::uuid(),
                'customer_name' => fake()->name(),
                'mpesa_number' => '07' . fake()->numerify('########'),
                'mpesa_code' => fake()->boolean(70) ? strtoupper(Str::random(10)) : null,
                'amount' => 0,
                'payment_status' => fake()->randomElement($paymentStatuses),
                'tracking_number' => fake()->boolean(40) ? 'TRK-' . fake()->numerify('######') : null,
                'town' => fake()->randomElement($towns),
                'description' => fake()->sentence(),
                'status' => fake()->randomElement($statuses),
            ]);

            // Add 1-4 items per order
            $itemCount = fake()->numberBetween(1, 4);
            $totalAmount = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();
                $quantity = fake()->numberBetween(1, 3);
                $price = $product->price;

                Item::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'size' => fake()->randomElement($product->sizes ?? ['40', '41', '42']),
                    'color' => fake()->randomElement($product->colors ?? ['black', 'white']),
                    'price' => $price,
                    'quantity' => $quantity,
                ]);

                $totalAmount += $price * $quantity;
            }

            // Update order total
            $order->update(['amount' => $totalAmount]);
        }

        $this->command->info('Created 20 orders with items.');
    }
}