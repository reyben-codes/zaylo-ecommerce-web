<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // Nullable so existing, free-text addresses remain intact.
            $table->string('region_code', 9)->nullable();
            $table->string('region_name')->nullable();
            $table->string('province_code', 9)->nullable();
            $table->string('city_municipality_code', 9)->nullable();
            $table->string('barangay_code', 9)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['region_code', 'region_name', 'province_code', 'city_municipality_code', 'barangay_code']);
        });
    }
};
