<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The logistics partner a buyer picks at checkout and the shipping zone between that partner's hub and the
 * delivery address, which sets the shipping fee (App\Services\ShippingRates).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'logistics_provider_id')) {
                $table->foreignId('logistics_provider_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'shipping_zone')) {
                $table->string('shipping_zone', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'logistics_provider_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('logistics_provider_id');
            });
        }
        if (Schema::hasColumn('orders', 'shipping_zone')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('shipping_zone');
            });
        }
    }
};
