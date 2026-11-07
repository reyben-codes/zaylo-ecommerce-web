<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguish the first-mile courier (who picks up parcels from sellers and brings them to the hub)
 * from the last-mile rider (who delivers from the hub to the buyer).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipments') && ! Schema::hasColumn('shipments', 'pickup_courier_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->foreignId('pickup_courier_id')->nullable()->after('logistics_provider_id')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipments') && Schema::hasColumn('shipments', 'pickup_courier_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['pickup_courier_id']);
                } catch (\Throwable) {
                    // Foreign key might not exist on SQLite.
                }
                try {
                    $table->dropIndex(['pickup_courier_id']);
                } catch (\Throwable) {
                    // Index might already be dropped.
                }
                $table->dropColumn('pickup_courier_id');
            });
        }
    }
};

