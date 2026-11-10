<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cod_financial_transactions')) {
            return;
        }
        Schema::create('cod_financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logistics_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 24)->default('completed');
            $table->decimal('amount', 12, 2);
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            // A lifecycle event is written once for each parcel; corrections are separate audited records, not edits.
            $table->unique(['shipment_id', 'type']);
            $table->index(['order_id', 'occurred_at']);
            $table->index(['to_user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_financial_transactions');
    }
};
