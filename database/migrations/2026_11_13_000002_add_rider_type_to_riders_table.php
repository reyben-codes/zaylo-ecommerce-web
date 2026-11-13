<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('riders') && ! Schema::hasColumn('riders', 'rider_type')) {
            Schema::table('riders', function (Blueprint $table) {
                // 'pickup' for PickupRider (first-mile), 'delivery' for DeliveryRider (last-mile)
                $table->string('rider_type', 20)->default('delivery')->after('vehicle_type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('riders') && Schema::hasColumn('riders', 'rider_type')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->dropColumn('rider_type');
            });
        }
    }
};

