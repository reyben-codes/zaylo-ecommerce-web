<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments ZAYLO makes to logistics partners: their share of the shipping fee of each parcel they delivered.
 * Each delivered parcel points at the payout that paid it, so no parcel is paid twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_payouts')) {
            Schema::create('logistics_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('logistics_provider_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('parcels');
                // Full shipping fees of the parcels, the partner's share of them, and the share rate used.
                $table->decimal('shipping_fees', 12, 2);
                $table->decimal('amount', 12, 2);
                $table->decimal('partner_share', 4, 2);
                // e.g. the bank or GCash reference of the transfer.
                $table->string('reference', 100)->nullable();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at');
                $table->timestamps();

                $table->index(['logistics_provider_id', 'paid_at']);
            });
        }

        if (Schema::hasTable('shipments') && ! Schema::hasColumn('shipments', 'logistics_payout_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->foreignId('logistics_payout_id')->nullable()->constrained('logistics_payouts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'logistics_payout_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('logistics_payout_id');
            });
        }
        Schema::dropIfExists('logistics_payouts');
    }
};
