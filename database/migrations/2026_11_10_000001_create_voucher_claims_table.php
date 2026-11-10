<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which buyers claim which vouchers.
 *
 * When a buyer claims a voucher, they reserve an eligibility to use it.
 * If usage_limit is specified on the voucher (e.g. 1, 5, etc.), the total number
 * of claims across all buyers cannot exceed that limit. Once claimed up to that limit,
 * no other buyer can claim it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('voucher_claims')) {
            Schema::create('voucher_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('claimed_at')->useCurrent();
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->unique(['voucher_id', 'user_id']);
                $table->index(['user_id']);
                $table->index(['voucher_id', 'used_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_claims');
    }
};
