<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('user_id')->constrained('logistics_providers')->nullOnDelete();
            $table->string('network_brand', 40)->nullable()->after('parent_id')->index();
            $table->string('branch_name', 150)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['network_brand', 'branch_name']);
        });
    }
};

