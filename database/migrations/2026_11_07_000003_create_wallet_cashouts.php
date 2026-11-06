<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics partners turn their wallet credits into GCash (riders withdraw through rider_withdrawals instead). The
 * transfer is simulated (no GCash API account), so each cash-out is recorded with a demo reference and the wallet
 * balance goes down by its amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_cashouts')) {
            return;
        }
        Schema::create('wallet_cashouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('gcash_number', 11);
            $table->string('gcash_name', 100);
            $table->string('reference', 40)->unique();
            $table->string('status', 20)->default('sent');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_cashouts');
    }
};
