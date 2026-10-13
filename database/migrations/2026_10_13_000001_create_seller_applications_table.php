<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shop, business, pickup address and document details a seller submits for admin approval.
     */
    public function up(): void
    {
        Schema::create('seller_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('shop_name', 30)->unique();
            $table->string('shop_category', 50);
            $table->string('seller_type', 30);
            $table->string('tin', 15)->unique();
            $table->string('region_code', 9);
            $table->string('region_name');
            $table->string('province_code', 9)->nullable();
            $table->string('province_name')->nullable();
            $table->string('city_code', 9);
            $table->string('city_name');
            $table->string('barangay_code', 9);
            $table->string('barangay_name');
            $table->string('street_address');
            $table->string('postal_code', 4);
            $table->string('government_id_path')->nullable();
            $table->string('dti_certificate_path')->nullable();
            $table->string('sec_certificate_path')->nullable();
            $table->string('bir_2303_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_applications');
    }
};
