<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep existing deployed order tables intact. New installs retain seller_orders and minor-unit totals.
        if (! Schema::hasColumn('orders', 'user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('status')->default('placed')->index();
                $table->string('recipient_name')->nullable();
                $table->string('phone')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('placed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('payment_method')->change();
            });
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'provider')) $table->string('provider')->nullable();
            if (! Schema::hasColumn('payments', 'currency')) $table->string('currency', 3)->default('PHP');
            if (! Schema::hasColumn('payments', 'idempotency_key')) $table->string('idempotency_key')->nullable()->unique();
            if (! Schema::hasColumn('payments', 'payload')) $table->json('payload')->nullable();
            if (! Schema::hasColumn('payments', 'paid_at')) $table->timestamp('paid_at')->nullable();
        });
        if (! Schema::hasTable('order_status_histories')) {
            Schema::create('order_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Forward-only compatibility change: rollback must not discard order or payment audit data.
    }
};
