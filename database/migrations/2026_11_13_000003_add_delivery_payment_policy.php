<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('logistics_providers', 'rider_rate_minor')) {
            Schema::table('logistics_providers', fn (Blueprint $table) => $table->unsignedInteger('rider_rate_minor')->default(4000));
        }
        // Null means the original policy. Never recalculate historical transactions.
        foreach (['orders', 'seller_orders', 'logistics_cod_settlements'] as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'settlement_breakdown')) {
                Schema::table($name, fn (Blueprint $table) => $table->json('settlement_breakdown')->nullable());
            }
        }
        if (Schema::hasTable('logistics_cod_settlements') && ! Schema::hasColumn('logistics_cod_settlements', 'admin_reference')) {
            Schema::table('logistics_cod_settlements', function (Blueprint $table) {
                $table->string('admin_reference', 100)->nullable();
                $table->timestamp('admin_settled_at')->nullable();
                $table->unsignedBigInteger('admin_settled_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('logistics_cod_settlements') && Schema::hasColumn('logistics_cod_settlements', 'admin_reference')) {
            Schema::table('logistics_cod_settlements', fn (Blueprint $table) => $table->dropColumn(['admin_reference', 'admin_settled_at', 'admin_settled_by']));
        }
        foreach (['orders', 'seller_orders', 'logistics_cod_settlements'] as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'settlement_breakdown')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('settlement_breakdown'));
            }
        }
        if (Schema::hasColumn('logistics_providers', 'rider_rate_minor')) {
            Schema::table('logistics_providers', fn (Blueprint $table) => $table->dropColumn('rider_rate_minor'));
        }
    }
};
