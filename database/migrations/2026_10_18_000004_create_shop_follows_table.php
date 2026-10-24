<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buyers following shops. shop_id is the id products.seller_id points to: the seller's user id on the
        // deployed schema, the sellers row on the newer one, so it has no foreign key.
        Schema::create('shop_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shop_id');
            $table->timestamps();
            $table->unique(['user_id', 'shop_id']);
            $table->index('shop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_follows');
    }
};
