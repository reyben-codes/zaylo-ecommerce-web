<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyers can review a product from its page without having bought it. Those reviews have no order item and are not
 * marked "Verified purchase"; each buyer writes at most one per product (App\Services\ProductReviews).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->change();
            $table->index(['product_id', 'user_id']);
        });
    }

    public function down(): void
    {
        DB::table('product_reviews')->whereNull('order_item_id')->delete();
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'user_id']);
            $table->foreignId('order_item_id')->nullable(false)->change();
        });
    }
};
