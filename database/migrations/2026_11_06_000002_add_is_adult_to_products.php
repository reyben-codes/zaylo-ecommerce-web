<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 18+ products: the seller marks a product as for adults only. Buyers no longer need an approved ID to shop; they need
 * one only to order a product marked 18+ (App\Services\CheckoutService::ensureCanOrder).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'is_adult')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_adult')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'is_adult')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_adult');
            });
        }
    }
};
