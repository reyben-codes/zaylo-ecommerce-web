<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shops can sell in several categories, so seller registration no longer asks for one. Shops that registered with a
 * category keep it; new applications have none.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->string('shop_category', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('seller_applications')->whereNull('shop_category')
            ->update(['shop_category' => array_key_first(config('marketplace.categories'))]);
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->string('shop_category', 50)->nullable(false)->change();
        });
    }
};
