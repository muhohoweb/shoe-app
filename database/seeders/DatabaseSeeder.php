<?php

namespace Database\Seeders;

use App\Models\DeliveryLocation;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

//        User::query()->create([
//            'name' => 'Test User',
//            'email' => 'app@gmail.com',
//            'password'=>bcrypt('363WAIs7ce6M')
//        ]);

        $this->call([
            DeliveryLocationSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            OrderSeeder::class
        ]);
    }
}
