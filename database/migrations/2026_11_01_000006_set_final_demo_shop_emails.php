<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Each address is the shop's own; the zaylo-zyl.shop ones are forwarded to a Gmail inbox by Cloudflare Email Routing.
    private const EMAILS = [
        'Daily Kitchen Co.' => 'dailykitchenco.shop@gmail.com',
        'Homestead Living' => 'homesteadliving1.shop@gmail.com',
        'Greenhand Supply' => 'greenhandsupply.shop@gmail.com',
        'Steadfast Works' => 'steadfast-works@zaylo-zyl.shop',
        'Softnest Linens' => 'softnest-linens@zaylo-zyl.shop',
    ];

    public function up(): void
    {
        // Only the deployed schema keeps shop names in seller_profiles.
        if (! Schema::hasTable('seller_profiles')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (self::EMAILS as $shop => $email) {
                $ownerId = DB::table('seller_profiles')->where('store_name', $shop)->value('user_id');
                if (! $ownerId) {
                    echo "{$shop}: shop not found, skipped".PHP_EOL;
                    continue;
                }
                DB::table('users')->where('id', $ownerId)->update(['email' => $email, 'updated_at' => now()]);
                echo "{$shop}: {$email}".PHP_EOL;
            }
        });
    }

    // The earlier addresses were only placeholders, so there is nothing useful to go back to.
    public function down(): void
    {
    }
};
