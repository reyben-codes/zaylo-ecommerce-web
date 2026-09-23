<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['buyer', 'seller', 'logistics', 'rider', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Test accounts for each role
        User::create([
            'name'     => 'Test Buyer',
            'email'    => 'buyer@zaylo.com',
            'password' => Hash::make('password'),
            'role'     => 'buyer',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $seller = User::create([
            'name'     => 'Happy Heels Seller',
            'email'    => 'seller@zaylo.com',
            'password' => Hash::make('password'),
            'role'     => 'seller',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $seller->sellers()->create([
            'name' => 'Happy Heels',
            'slug' => 'happy-heels',
            'status' => 'approved',
        ]);

        $logisticsOwner = User::create([
            'name'     => 'Test Logistics Provider',
            'email'    => 'logistics@zaylo.com',
            'password' => Hash::make('password'),
            'role'     => 'logistics',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $provider = LogisticsProvider::create([
            'user_id' => $logisticsOwner->id,
            'name' => 'ZAYLO Express',
            'slug' => 'zaylo-express',
            'status' => 'approved',
            'contact_phone' => '09170000000',
        ]);

        $riderUser = User::create([
            'name'     => 'Test Rider',
            'email'    => 'courier@zaylo.com',
            'password' => Hash::make('password'),
            'role'     => 'rider',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        Rider::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'TEST-001',
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
        $this->call(DriveProductSeeder::class);

        $this->call(VoucherSeeder::class);
    }
}
