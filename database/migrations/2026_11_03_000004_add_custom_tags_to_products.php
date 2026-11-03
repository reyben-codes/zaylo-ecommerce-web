<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sellers can type their own tags under "Other" in each main category of the product form, stored as
 * {"home_garden": ["Bathroom Caddies"], ...}. Buyers see them with the product's categories and can search for them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('custom_tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('custom_tags');
        });
    }
};
