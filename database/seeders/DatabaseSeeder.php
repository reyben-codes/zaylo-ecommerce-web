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
        User::create([
            'name'     => 'Test Buyer',
            'email'    => 'buyer@zaylo.com',
            'password' => Hash::make('password'),
            'role'     => 'buyer',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        User::create([
            'name'     => 'Test Seller',
            'email'    => 'seller@zaylo.com',
            'password' => Hash::make('password'),
            'role'     => 'seller',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        User::create([
            'name'     => 'Test Courier',
            'email'    => 'courier@zaylo.com',
            'password' => Hash::make('password'),
            'role'     => 'courier',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@zaylo.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        // Sample products
        $this->call(ProductSeeder::class);
        $this->call(GardeningProductSeeder::class);
    }
}
