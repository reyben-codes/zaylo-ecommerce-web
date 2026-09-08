<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
            $table->string('sku')->nullable()->unique();
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->unsignedInteger('weight_grams')->nullable();
        });

        // Preserve access for administrators created before email verification existed.
        DB::table('users')->where('role', 'admin')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);

        DB::table('products')->orderBy('id')->get()->each(function ($product) {
            DB::table('products')->where('id', $product->id)->update([
                'slug' => Str::slug($product->name).'-'.$product->id,
                'sku' => 'ZAY-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropUnique(['sku']);
            $table->dropColumn(['slug', 'sku', 'low_stock_threshold', 'weight_grams']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'approved_at', 'approved_by']);
        });
    }
};
