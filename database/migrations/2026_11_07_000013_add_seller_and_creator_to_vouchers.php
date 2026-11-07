<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admins can target vouchers to a specific seller or all sellers; sellers can also create vouchers for their own shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                if (! Schema::hasColumn('vouchers', 'seller_id')) {
                    $table->unsignedBigInteger('seller_id')->nullable()->after('funded_by')->index();
                }
                if (! Schema::hasColumn('vouchers', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('seller_id')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                if (Schema::hasColumn('vouchers', 'created_by')) {
                    $table->dropConstrainedForeignId('created_by');
                }
                if (Schema::hasColumn('vouchers', 'seller_id')) {
                    try {
                        $table->dropIndex(['seller_id']);
                    } catch (\Throwable) {
                        // Index might not exist or already dropped.
                    }
                    $table->dropColumn('seller_id');
                }
            });
        }
    }
};
