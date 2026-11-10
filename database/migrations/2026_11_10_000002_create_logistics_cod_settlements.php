<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('logistics_cod_settlements')) {
            return;
        }

        Schema::create('logistics_cod_settlements', function (Blueprint $table) {
            $table->id();
            // A shipment can be allocated only once, even if a handover request is retried.
            $table->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rider_cash_handover_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('logistics_provider_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->foreignId('seller_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('product_price', 12, 2);
            $table->decimal('amount_received', 12, 2);
            $table->decimal('rider_earnings', 12, 2);
            $table->decimal('logistics_earnings', 12, 2);
            $table->decimal('admin_shipping_earnings', 12, 2);
            $table->decimal('admin_product_commission', 12, 2);
            $table->decimal('admin_earnings', 12, 2);
            $table->decimal('seller_earnings', 12, 2);
            $table->string('status', 32)->default('awaiting_seller_transfer');
            $table->string('seller_reference', 100)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['logistics_provider_id', 'status']);
            $table->index(['seller_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_cod_settlements');
    }
};
