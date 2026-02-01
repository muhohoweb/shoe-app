<?php

namespace Database\Seeders;

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

        User::query()->create([
            'name' => 'Test User',
            'email' => 'app@gmail.com',
            'password'=>bcrypt('5xM0I73Em5gN')
        ]);

        $this->call([
            CategorySeeder::class,
            ProductSeeder::class
        ]);
    }
}
