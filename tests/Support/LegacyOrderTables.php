<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The deployed database still uses the original orders, order items,
 * payments, status history and shipments columns, while the migrations build
 * the newer schema. Tests of code that runs against the deployed database
 * recreate those tables in their original shape.
 */
trait LegacyOrderTables
{
    protected function useLegacyOrderTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['shipments', 'order_items', 'payments', 'order_status_histories', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->foreignId('user_id');
            $table->foreignId('seller_id')->nullable();
            $table->string('status');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('currency')->nullable();
            $table->string('payment_method');
            $table->string('recipient_name');
            $table->string('phone');
            $table->text('shipping_address');
            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('voucher_code')->nullable();
            $table->decimal('shipping_discount', 10, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('courier_id')->nullable();
            $table->string('tracking_number');
            $table->string('status');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('product_id')->nullable();
            $table->foreignId('product_variant_id')->nullable();
            $table->foreignId('seller_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->string('provider')->nullable();
            $table->string('status');
            $table->decimal('amount', 10, 2);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('changed_by')->nullable();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }
}
