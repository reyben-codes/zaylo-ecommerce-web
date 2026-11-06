<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Map pins: the exact spot a buyer pins on a saved address, copied to each order at checkout so the rider finds the
 * door, and the rider's last reported position on the parcels they have out for delivery, which the buyer tracks
 * (App\Services\ParcelTracking).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['addresses' => 'latitude', 'orders' => 'delivery_latitude'] as $table => $latitude) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, $latitude)) {
                $longitude = str_replace('latitude', 'longitude', $latitude);
                Schema::table($table, function (Blueprint $table) use ($latitude, $longitude) {
                    $table->decimal($latitude, 10, 7)->nullable();
                    $table->decimal($longitude, 10, 7)->nullable();
                });
            }
        }

        if (Schema::hasTable('shipments') && ! Schema::hasColumn('shipments', 'rider_latitude')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->decimal('rider_latitude', 10, 7)->nullable();
                $table->decimal('rider_longitude', 10, 7)->nullable();
                $table->timestamp('rider_located_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'addresses' => ['latitude', 'longitude'],
            'orders' => ['delivery_latitude', 'delivery_longitude'],
            'shipments' => ['rider_latitude', 'rider_longitude', 'rider_located_at'],
        ];
        foreach ($columns as $table => $names) {
            if (Schema::hasColumn($table, $names[0])) {
                Schema::table($table, fn (Blueprint $table) => $table->dropColumn($names));
            }
        }
    }
};
