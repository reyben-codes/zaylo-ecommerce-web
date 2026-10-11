<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            // Nullable only so the column can be added to existing rows;
            // RegisterRiderRequest always requires it.
            $table->string('plate_number', 6)->nullable()->unique()->after('vehicle_type');
        });
    }

    public function down(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->dropUnique(['plate_number']);
            $table->dropColumn('plate_number');
        });
    }
};
