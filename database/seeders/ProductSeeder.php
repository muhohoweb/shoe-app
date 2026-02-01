<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Grab leaf (sub) categories only
        $subcategories = Category::query()->whereNotNull('parent_id')->get();

        $products = [
            ['name' => 'Air Max Pro', 'price' => 129.99, 'colors' => ['black', 'white'], 'sizes' => ['40', '41', '42', '43']],
            ['name' => 'Urban Runner X', 'price' => 99.99, 'colors' => ['red', 'blue'], 'sizes' => ['39', '40', '41', '42']],
            ['name' => 'Classic Leather Boot', 'price' => 189.99, 'colors' => ['brown', 'black'], 'sizes' => ['41', '42', '43', '44']],
            ['name' => 'Summer Slide', 'price' => 49.99, 'colors' => ['white', 'green'], 'sizes' => ['38', '39', '40', '41']],
            ['name' => 'Velvet Heel 6"', 'price' => 79.99, 'colors' => ['black', 'red'], 'sizes' => ['36', '37', '38', '39']],
            ['name' => 'Comfort Flat Slip-On', 'price' => 59.99, 'colors' => ['beige', 'black'], 'sizes' => ['36', '37', '38']],
            ['name' => 'Kids Speed Dash', 'price' => 39.99, 'colors' => ['blue', 'yellow'], 'sizes' => ['28', '29', '30', '31']],
            ['name' => 'Toddler First Step', 'price' => 29.99, 'colors' => ['pink', 'white'], 'sizes' => ['20', '21', '22']],
        ];

        foreach ($products as $i => $product) {
            $category = $subcategories[$i % $subcategories->count()];
            $slug = Str::slug($product['name']);

            Product::query()->create([
                'name' => $product['name'],
                'category_id' => $category->id,
                'description' => "A stylish {$product['name']} from our {$category->name} collection.",
                'price' => $product['price'],
                'stock' => rand(5, 50),
                'sku' => 'SKU-' . strtoupper(Str::random(8)),
                'slug' => $slug,
                'colors' => $product['colors'],
                'sizes' => $product['sizes'],
                'is_active' => true,
                'status' => 'active',
            ]);
        }
    }
}