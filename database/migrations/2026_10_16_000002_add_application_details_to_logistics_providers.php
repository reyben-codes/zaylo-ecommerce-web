<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business registration, office address, service regions and documents a logistics partner submits for admin approval.
     * Existing partners keep empty details; they are asked for them only if they are rejected and resubmit.
     */
    public function up(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->string('sec_registration_no', 11)->nullable()->unique()->after('name');
            $table->string('tin', 15)->nullable()->unique()->after('sec_registration_no');
            $table->string('contact_position', 100)->nullable()->after('contact_phone');
            $table->json('service_areas')->nullable()->after('service_area');
            $table->string('region_code', 9)->nullable();
            $table->string('region_name')->nullable();
            $table->string('province_code', 9)->nullable();
            $table->string('province_name')->nullable();
            $table->string('city_code', 9)->nullable();
            $table->string('city_name')->nullable();
            $table->string('barangay_code', 9)->nullable();
            $table->string('barangay_name')->nullable();
            $table->string('street_address')->nullable();
            $table->string('postal_code', 4)->nullable();
            $table->string('sec_certificate_path')->nullable();
            $table->string('bir_2303_path')->nullable();
            $table->string('business_permit_path')->nullable();
            $table->string('representative_id_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->dropUnique(['sec_registration_no']);
            $table->dropUnique(['tin']);
            $table->dropColumn([
                'sec_registration_no', 'tin', 'contact_position', 'service_areas', 'region_code', 'region_name', 'province_code', 'province_name',
                'city_code', 'city_name', 'barangay_code', 'barangay_name', 'street_address', 'postal_code',
                'sec_certificate_path', 'bir_2303_path', 'business_permit_path', 'representative_id_path', 'submitted_at', 'reviewed_at',
            ]);
        });
    }
};
