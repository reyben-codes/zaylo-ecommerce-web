<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rider wallets: a commission for every parcel a rider delivers, part of the logistics partner's share of the
 * shipping fee, and the rider's GCash withdrawals, which the rider's logistics partner sends and records. A rider's
 * balance is their commissions less every withdrawal that was not rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rider_commissions')) {
            Schema::create('rider_commissions', function (Blueprint $table) {
                $table->id();
                // The rider's account; parcels on either schema name the rider through it.
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                // One commission per parcel, so a parcel is never credited twice.
                $table->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('logistics_provider_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 10, 2);
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('rider_withdrawals')) {
            Schema::create('rider_withdrawals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                // The rider's logistics partner when they asked; it sends the money.
                $table->foreignId('logistics_provider_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('gcash_number', 11);
                $table->string('gcash_name', 100);
                // pending, paid or rejected.
                $table->string('status', 20)->default('pending');
                // The GCash reference number of the transfer.
                $table->string('reference', 100)->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['logistics_provider_id', 'status']);
            });
        }

        // How much of each payout to a partner is its riders' commissions.
        if (Schema::hasTable('logistics_payouts') && ! Schema::hasColumn('logistics_payouts', 'rider_commissions')) {
            Schema::table('logistics_payouts', function (Blueprint $table) {
                $table->decimal('rider_commissions', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('logistics_payouts', 'rider_commissions')) {
            Schema::table('logistics_payouts', function (Blueprint $table) {
                $table->dropColumn('rider_commissions');
            });
        }
        Schema::dropIfExists('rider_withdrawals');
        Schema::dropIfExists('rider_commissions');
    }
};
