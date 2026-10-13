<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesPasswords;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProvisionSeller extends Command
{
    use GeneratesPasswords;

    protected $signature = 'seller:provision
        {email : Email address used on the seller login page}
        {--name= : Seller full name (new accounts only)}
        {--store= : Store name shown to buyers}
        {--phone= : Mobile number, 09XXXXXXXXX}
        {--reset-password : Replace the password of an existing seller account}';

    protected $description = 'Create or repair an approved, verified seller account that can sign in at /seller/login';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $input = ['email' => $email, 'phone' => $this->option('phone'), 'store' => $this->option('store')];
        $validator = Validator::make($input, [
            'email' => 'required|email:rfc',
            'phone' => ['nullable', 'regex:/^09[0-9]{9}$/'],
            'store' => 'nullable|string|min:2|max:120',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::INVALID;
        }

        $user = User::where('email', $email)->first();
        if ($user && ! $user->hasRole('seller')) {
            $this->error("{$email} already belongs to a {$user->role} account. Use a different email for the seller.");

            return self::FAILURE;
        }

        $password = (! $user || $this->option('reset-password')) ? $this->generatePassword() : null;

        DB::transaction(function () use (&$user, $email, $password) {
            $attributes = ['status' => 'active'];
            if (Schema::hasColumn('users', 'is_suspended')) {
                $attributes['is_suspended'] = false;
            }
            if (Schema::hasColumn('users', 'approved_at')) {
                $attributes['approved_at'] = $user?->approved_at ?? now();
            }
            if ($this->option('phone')) {
                $attributes['phone'] = $this->option('phone');
            }

            if (! $user) {
                $user = User::create($attributes + [
                    'name' => $this->option('name') ?: 'ZAYLO Seller',
                    'email' => $email,
                    'password' => $password,
                    'role' => 'seller',
                ]);
            } else {
                $user->fill($attributes);
                if ($password) {
                    $user->password = $password;
                    $user->remember_token = Str::random(60);
                }
                $user->save();
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $storeName = $this->option('store');
            if (Product::usesLegacySchema()) {
                $profile = $user->sellerProfile ?? new SellerProfile(['user_id' => $user->id]);
                $profile->store_name = $storeName ?: ($profile->store_name ?? $user->name."'s Store");
                $profile->save();
            } else {
                $shop = $user->sellerShop();
                $shop->update(array_filter(['status' => 'approved', 'name' => $storeName]));
            }
        });

        $this->info($password ? 'Seller account ready.' : 'Seller account repaired. Its password was not changed.');
        $this->table(['Field', 'Value'], array_filter([
            ['Login page', route('seller.login')],
            ['Email', $user->email],
            $password ? ['Password', $password] : null,
            ['Store', $user->sellerStoreName()],
        ]));
        if ($password) {
            $this->warn('This password is shown only once. Store it in a password manager, then change it from Seller Center > Account.');
        }

        return self::SUCCESS;
    }
}
