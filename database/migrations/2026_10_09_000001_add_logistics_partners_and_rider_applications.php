<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics partners (approved by administrators) and the riders who apply to them.
 * Written to upgrade both the deployed database, which predates these tables and stores
 * roles in an enum column, and fresh installs, where the platform migration created them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            // The deployed enum cannot hold "rider" or "logistics", which registration now uses.
            Schema::table('users', fn (Blueprint $table) => $table->string('role', 20)->default('buyer')->change());
            DB::table('users')->where('role', 'courier')->update(['role' => 'rider']);
        }

        if (! Schema::hasTable('logistics_providers')) {
            Schema::create('logistics_providers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status', 20)->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->string('contact_phone', 30)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        Schema::table('logistics_providers', function (Blueprint $table) {
            if (! Schema::hasColumn('logistics_providers', 'service_area')) {
                $table->string('service_area')->nullable();
            }
            if (! Schema::hasColumn('logistics_providers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });

        if (! Schema::hasTable('riders')) {
            Schema::create('riders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('logistics_provider_id')->constrained()->cascadeOnDelete();
                $table->string('vehicle_type')->nullable();
                $table->string('plate_no')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['logistics_provider_id', 'is_active']);
            });
        }

        Schema::table('riders', function (Blueprint $table) {
            if (! Schema::hasColumn('riders', 'status')) {
                $table->string('status', 20)->default('pending')->index();
            }
            if (! Schema::hasColumn('riders', 'license_number')) {
                $table->string('license_number', 40)->nullable();
            }
            if (! Schema::hasColumn('riders', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
            if (! Schema::hasColumn('riders', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('riders', 'status')) {
            Schema::table('riders', fn (Blueprint $table) => $table->dropIndex(['status']));
        }
        Schema::table('riders', function (Blueprint $table) {
            foreach (['status', 'license_number', 'rejection_reason', 'reviewed_at'] as $column) {
                if (Schema::hasColumn('riders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('logistics_providers', function (Blueprint $table) {
            foreach (['service_area', 'approved_at'] as $column) {
                if (Schema::hasColumn('logistics_providers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        // The role column stays a string: narrowing it back to an enum could reject existing rider and logistics accounts.
    }
};
