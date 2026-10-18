<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extra categories a product is also listed under, keyed by the marketplace category slug so it
        // works with both the deployed products table (category column) and the normalized one (category_id).
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('category', 50);
            $table->timestamps();
            $table->unique(['product_id', 'category']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
