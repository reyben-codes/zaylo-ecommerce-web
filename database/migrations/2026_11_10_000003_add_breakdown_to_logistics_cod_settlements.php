<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Supports installations where the settlement table existed before the detailed Admin COD dashboard fields. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_cod_settlements')) {
            return;
        }

        foreach (['product_price', 'rider_earnings', 'admin_shipping_earnings', 'admin_product_commission'] as $column) {
            if (! Schema::hasColumn('logistics_cod_settlements', $column)) {
                Schema::table('logistics_cod_settlements', fn (Blueprint $table) => $table->decimal($column, 12, 2)->default(0));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('logistics_cod_settlements')) {
            return;
        }

        foreach (['product_price', 'rider_earnings', 'admin_shipping_earnings', 'admin_product_commission'] as $column) {
            if (Schema::hasColumn('logistics_cod_settlements', $column)) {
                Schema::table('logistics_cod_settlements', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
