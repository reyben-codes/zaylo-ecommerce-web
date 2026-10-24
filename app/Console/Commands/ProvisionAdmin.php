<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesPasswords;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProvisionAdmin extends Command
{
    use GeneratesPasswords;

    protected $signature = 'admin:provision
        {email : Email address used to sign in at /login}
        {--name= : Administrator full name (new accounts only)}
        {--reset-password : Replace the password of an existing administrator}';

    protected $description = 'Create or repair a verified, active administrator account';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email:rfc'])->fails()) {
            $this->error('Enter a valid email address.');

            return self::INVALID;
        }

        $user = User::where('email', $email)->first();
        if ($user && ! $user->hasRole('admin')) {
            $this->error("{$email} already belongs to a {$user->role} account. Use a different email for the administrator.");

            return self::FAILURE;
        }

        $password = (! $user || $this->option('reset-password')) ? $this->generatePassword() : null;

        DB::transaction(function () use (&$user, $email, $password) {
            $attributes = ['status' => 'active'];
            if (Schema::hasColumn('users', 'is_suspended')) {
                $attributes['is_suspended'] = false;
            }

            if (! $user) {
                $user = User::create($attributes + [
                    'name' => $this->option('name') ?: 'ZAYLO Administrator',
                    'email' => $email,
                    'password' => $password,
                    'role' => 'admin',
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
        });

        $this->info($password ? 'Administrator account ready.' : 'Administrator account repaired. Its password was not changed.');
        $this->table(['Field', 'Value'], array_filter([
            ['Login page', route('admin.login')],
            ['Email', $user->email],
            $password ? ['Password', $password] : null,
        ]));
        if ($password) {
            $this->warn('This password is shown only once. Store it in a password manager, then change it from Admin Center > Account.');
        }

        return self::SUCCESS;
    }
}
