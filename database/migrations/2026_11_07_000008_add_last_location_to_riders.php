<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each rider last was, as their app reports it while they are on shift or out for delivery. Logistics uses it to
 * offer the nearest riders first when assigning parcels (App\Services\RiderProximity).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('riders') || Schema::hasColumn('riders', 'last_latitude')) {
            return;
        }
        Schema::table('riders', function (Blueprint $table) {
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->timestamp('last_located_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('riders', 'last_latitude')) {
            Schema::table('riders', fn (Blueprint $table) => $table->dropColumn(['last_latitude', 'last_longitude', 'last_located_at']));
        }
    }
};
