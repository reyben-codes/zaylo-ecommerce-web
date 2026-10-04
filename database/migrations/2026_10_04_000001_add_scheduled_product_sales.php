<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_type', 12)->nullable();
            $table->decimal('sale_value', 10, 2)->nullable();
            $table->dateTime('sale_starts_at')->nullable();
            $table->dateTime('sale_ends_at')->nullable();
            $table->unsignedBigInteger('sale_variant_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'sale_type', 'sale_value', 'sale_starts_at', 'sale_ends_at', 'sale_variant_id',
        ]));
    }
};
