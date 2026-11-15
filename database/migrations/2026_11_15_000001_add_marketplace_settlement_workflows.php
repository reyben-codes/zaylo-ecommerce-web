<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 150);
            $table->string('section', 40);
            $table->string('title');
            $table->text('body');
            $table->string('kind', 40);
            $table->unsignedBigInteger('entity_id')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_key']);
            $table->index(['user_id', 'section', 'read_at']);
        });
        Schema::create('escrow_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('checkout_session')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('gateway_payment')->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('gateway_fee_minor')->default(0);
            $table->boolean('livemode')->default(false);
            $table->string('status', 25)->default('pending');
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
        Schema::create('settlement_wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 25);
            $table->bigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['escrow_payment_id', 'user_id', 'kind']);
        });
        Schema::create('seller_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('method', 20);
            $table->string('account_number', 50);
            $table->string('account_name', 100);
            $table->string('status', 25)->default('pending');
            $table->string('reference', 13)->nullable()->unique();
            $table->boolean('livemode')->default(false);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('rider_withdrawals', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable();
        });
        // Keep the original delivery commission table intact for historical orders.
        Schema::create('rider_leg_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('logistics_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('leg', 20);
            $table->decimal('amount', 10, 2);
            $table->decimal('distance_km', 10, 3);
            $table->decimal('weight_kg', 10, 3);
            $table->timestamps();
            $table->unique(['shipment_id', 'leg']);
            $table->index(['user_id', 'created_at']);
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->json('rider_fee_quote')->nullable();
            $table->timestamp('rider_handover_confirmed_at')->nullable();
            $table->timestamp('delivery_scanned_at')->nullable();
            $table->timestamp('delivery_handover_confirmed_at')->nullable();
        });
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->decimal('hub_latitude', 10, 7)->nullable();
            $table->decimal('hub_longitude', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        // Financial records intentionally survive application rollback.
    }
};
