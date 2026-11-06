<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The GCash account a rider saves in the app, which their wallet withdrawals are sent to (App\Services\RiderWallet).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('riders') && ! Schema::hasColumn('riders', 'gcash_number')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->string('gcash_number', 11)->nullable();
                $table->string('gcash_name', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('riders', 'gcash_number')) {
            Schema::table('riders', fn (Blueprint $table) => $table->dropColumn(['gcash_number', 'gcash_name']));
        }
    }
};
