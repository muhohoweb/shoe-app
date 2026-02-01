<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = [
            ['name' => 'Men'],
            ['name' => 'Women'],
            ['name' => 'Kids'],
        ];

        foreach ($categories as $category) {
            $parent = Category::query()->create($category);

            // Subcategories per parent
            $subs = match($parent->name) {
                'Men' => ['Sneakers', 'Boots', 'Sandals', 'Formal'],
                'Women' => ['Heels', 'Flats', 'Sneakers', 'Boots'],
                'Kids' => ['Boys', 'Girls', 'Toddlers'],
            };

            foreach ($subs as $sub) {
                Category::query()->create([
                    'name' => $sub,
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}