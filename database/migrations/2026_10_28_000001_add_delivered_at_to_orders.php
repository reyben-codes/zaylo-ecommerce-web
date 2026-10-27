<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the rider delivered the order, on the deployed schema. The buyer then confirms they received it, or the
     * order completes on its own a few days later (App\Services\OrderReceipts).
     */
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'delivered_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('delivered_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'delivered_at')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivered_at'));
        }
    }
};
