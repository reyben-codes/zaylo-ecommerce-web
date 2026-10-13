<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesPasswords;
use App\Models\LogisticsProvider;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProvisionLogistics extends Command
{
    use GeneratesPasswords;

    protected $signature = 'logistics:provision
        {email : Email address used to sign in at /login}
        {--name= : Account holder full name (new accounts only)}
        {--company= : Company name riders choose when they apply}
        {--phone= : Dispatch mobile number, 09XXXXXXXXX}
        {--area= : Service area, e.g. "Metro Manila"}
        {--reset-password : Replace the password of an existing logistics account}';

    protected $description = 'Create or repair an approved logistics partner account';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $validator = Validator::make(
            ['email' => $email, 'phone' => $this->option('phone'), 'company' => $this->option('company')],
            ['email' => 'required|email:rfc', 'phone' => ['nullable', 'regex:/^09[0-9]{9}$/'], 'company' => 'nullable|string|min:2|max:150'],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::INVALID;
        }

        $user = User::where('email', $email)->first();
        if ($user && ! $user->hasRole('logistics')) {
            $this->error("{$email} already belongs to a {$user->role} account. Use a different email for the logistics partner.");

            return self::FAILURE;
        }

        $password = (! $user || $this->option('reset-password')) ? $this->generatePassword() : null;

        DB::transaction(function () use (&$user, $email, $password) {
            $attributes = ['status' => 'active'];
            if (Schema::hasColumn('users', 'approved_at')) {
                $attributes['approved_at'] = $user?->approved_at ?? now();
            }
            if ($this->option('phone')) {
                $attributes['phone'] = $this->option('phone');
            }

            if (! $user) {
                $user = User::create($attributes + [
                    'name' => $this->option('name') ?: 'ZAYLO Logistics',
                    'email' => $email,
                    'password' => $password,
                    'role' => 'logistics',
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

            $provider = $user->logisticsProvider ?? new LogisticsProvider(['user_id' => $user->id]);
            $company = $this->option('company') ?: ($provider->name ?? $user->name.' Logistics');
            $provider->fill(array_filter([
                'name' => $company,
                'contact_phone' => $this->option('phone') ?: $provider->contact_phone,
                'service_area' => $this->option('area') ?: $provider->service_area,
            ]) + ['status' => 'approved', 'approved_at' => $provider->approved_at ?? now()]);
            $provider->slug ??= Str::slug($company).'-'.Str::lower(Str::random(6));
            $provider->save();
        });

        $this->info($password ? 'Logistics partner account ready.' : 'Logistics partner account repaired. Its password was not changed.');
        $this->table(['Field', 'Value'], array_filter([
            ['Login page', route('logistics.login')],
            ['Email', $user->email],
            $password ? ['Password', $password] : null,
            ['Company', $user->logisticsProvider()->first()->name],
        ]));
        if ($password) {
            $this->warn('This password is shown only once. Store it in a password manager, then change it from Logistics Center > Account.');
        }

        return self::SUCCESS;
    }
}
