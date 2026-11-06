<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop sections: groupings a seller makes for their own shop page ("New Arrivals", "Bundle Deals"). They only show
 * on that shop, never in the marketplace category menu. shop_id holds what products.seller_id holds (the seller's
 * user id on the deployed schema, the sellers row on the newer one), so it has no foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_sections')) {
            Schema::create('shop_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shop_id');
                $table->string('name', 30);
                $table->unsignedSmallInteger('position')->default(0);
                $table->boolean('is_visible')->default(true);
                $table->timestamps();

                $table->index(['shop_id', 'position']);
            });
        }

        if (! Schema::hasTable('product_shop_section')) {
            Schema::create('product_shop_section', function (Blueprint $table) {
                $table->foreignId('shop_section_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['shop_section_id', 'product_id']);
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_shop_section');
        Schema::dropIfExists('shop_sections');
    }
};
