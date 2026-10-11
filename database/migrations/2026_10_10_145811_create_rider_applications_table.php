<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents a rider submits when registering from the mobile app. Kept apart
 * from `riders`, which needs a logistics provider assigned once approved.
 * Image paths point at the private `local` disk, never the public one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('license_number', 20)->unique();
            $table->date('license_expires_on');
            $table->string('license_image_path');
            $table->string('vehicle_type', 20);
            $table->string('or_cr_image_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_applications');
    }
};
