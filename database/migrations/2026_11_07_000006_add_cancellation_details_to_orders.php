<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why and when a buyer cancelled an order (App\Support\BuyerOrderTabs::CANCEL_REASONS). The time also moves the order
 * from Cancelled to Purchase history after a couple of days.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'cancel_reason')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancel_reason', 40)->nullable();
            $table->string('cancel_note', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'cancel_reason')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['cancel_reason', 'cancel_note', 'cancelled_at']));
        }
    }
};
