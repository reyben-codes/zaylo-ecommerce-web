<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Test accounts for each role
        User::updateOrCreate(
            ['email' => 'buyer@zaylo.com'],
            [
                'name' => 'Test Buyer',
                'password' => Hash::make('password'),
                'role' => 'buyer',
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'seller@zaylo.com'],
            [
                'name' => 'Test Seller',
                'password' => Hash::make('password'),
                'role' => 'seller',
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'courier@zaylo.com'],
            [
                'name' => 'Test Courier',
                'password' => Hash::make('password'),
                'role' => 'courier',
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@zaylo.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        // Sample products
        $this->call(ProductSeeder::class);
        $this->call(GardeningProductSeeder::class);

        // Sample shops
        $this->call(ShopSeeder::class);
    }
}