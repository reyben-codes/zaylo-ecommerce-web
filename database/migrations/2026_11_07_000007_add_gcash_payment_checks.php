<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GCash payments without the GCash API: buyers send the money to the seller's own GCash and enter the reference
 * number; the seller checks it in their GCash app before confirming the order (App\Services\GcashPayments).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'gcash_number')) {
            Schema::table('users', function (Blueprint $table) {
                // The seller's GCash account that buyers pay.
                $table->string('gcash_number', 11)->nullable();
                $table->string('gcash_name', 100)->nullable();
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'gcash_reference')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('gcash_reference', 20)->nullable();
                $table->timestamp('reference_submitted_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->string('rejection_note', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'gcash_reference')) {
            Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['gcash_reference', 'reference_submitted_at', 'verified_at', 'verified_by', 'rejection_note']));
        }
        if (Schema::hasColumn('users', 'gcash_number')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['gcash_number', 'gcash_name']));
        }
    }
};
