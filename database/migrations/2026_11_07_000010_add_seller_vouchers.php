<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discount vouchers that shops opt into (App\Services\Vouchers). Admins create them; every seller is invited and joins
 * or declines. A discount voucher is paid by the shops that joined; a global free-shipping voucher is paid by ZAYLO and
 * needs no opt-in. Each order keeps the discount it got.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vouchers') && ! Schema::hasColumn('vouchers', 'value')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->string('name', 80)->nullable();
                // percent: value is the %, fixed: value is pesos; free_shipping ignores it.
                $table->decimal('value', 10, 2)->nullable();
                $table->decimal('min_spend', 10, 2)->nullable();
                $table->decimal('max_discount', 10, 2)->nullable();
                // zaylo (global free shipping) or seller (shops that joined pay the discount).
                $table->string('funded_by', 10)->default('zaylo');
            });
        }

        if (! Schema::hasTable('voucher_sellers')) {
            Schema::create('voucher_sellers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
                // The shop id orders carry (users.id on the deployed schema, sellers.id on the newer one).
                $table->unsignedBigInteger('seller_id');
                $table->string('status', 12)->default('pending');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->unique(['voucher_id', 'seller_id']);
                $table->index(['seller_id', 'status']);
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'voucher_discount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('voucher_discount', 10, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'voucher_discount')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('voucher_discount'));
        }
        Schema::dropIfExists('voucher_sellers');
        if (Schema::hasColumn('vouchers', 'value')) {
            Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn(['name', 'value', 'min_spend', 'max_discount', 'funded_by']));
        }
    }
};
