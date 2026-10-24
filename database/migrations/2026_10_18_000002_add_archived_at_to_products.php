<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Archived products are hidden from buyers but keep their reviews, photos, options and order links,
        // so a seller can restore them. Only products without orders or reviews can be deleted for good.
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_active');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
