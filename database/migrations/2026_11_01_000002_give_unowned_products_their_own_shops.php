<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Shop name => owner email and the main categories it handles.
    public const SHOPS = [
        'Daily Kitchen Co.' => ['daily-kitchen@zaylo-zyl.shop', ['kitchen_appliances']],
        'Homestead Living' => ['homestead-living@zaylo-zyl.shop', ['furniture_decor', 'home_garden']],
        'Greenhand Supply' => ['greenhand-supply@zaylo-zyl.shop', ['gardening_tools', 'outdoor_living']],
        'Steadfast Works' => ['steadfast-works@zaylo-zyl.shop', ['home_improvement_tools']],
        'Softnest Linens' => ['softnest-linens@zaylo-zyl.shop', ['bedding_bath']],
    ];

    public function up(): void
    {
        // Only the deployed schema keeps shop names in seller_profiles.
        if (! Schema::hasColumn('products', 'category') || ! Schema::hasTable('seller_profiles')) {
            return;
        }

        $tree = config('marketplace.category_tree');
        $logins = [];

        DB::transaction(function () use ($tree, &$logins): void {
            foreach (self::SHOPS as $shop => [$email, $mains]) {
                $ownerId = DB::table('users')->where('email', $email)->value('id');
                if (! $ownerId) {
                    $password = Str::password(16);
                    $ownerId = DB::table('users')->insertGetId([
                        'name' => $shop, 'email' => $email, 'password' => Hash::make($password), 'role' => 'seller',
                        'status' => 'active', 'email_verified_at' => now(), 'approved_at' => now(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $logins[] = "{$shop}: {$email} / {$password}";
                }
                DB::table('seller_profiles')->updateOrInsert(
                    ['user_id' => $ownerId],
                    ['store_name' => $shop, 'created_at' => now(), 'updated_at' => now()],
                );

                $categories = collect($mains)->flatMap(fn (string $main) => array_keys($tree[$main]['subcategories']))->all();
                $moved = DB::table('products as p')
                    ->leftJoin('users as u', 'u.id', '=', 'p.seller_id')
                    ->leftJoin('seller_profiles as s', 's.user_id', '=', 'p.seller_id')
                    ->whereIn('p.category', $categories)
                    ->where(fn ($query) => $query->whereNull('p.seller_id')->orWhereNull('u.id')->orWhereNull('s.store_name'))
                    ->pluck('p.id');
                DB::table('products')->whereIn('id', $moved)->update(['seller_id' => $ownerId, 'updated_at' => now()]);
                echo "{$shop}: {$moved->count()} products".PHP_EOL;
            }
        });

        // Generated here and stored only as hashes, so this is the one chance to see them.
        foreach ($logins as $login) {
            echo $login.PHP_EOL;
        }
    }

    // The previous owners aren't recorded, so products stay with their new shops.
    public function down(): void
    {
    }
};
