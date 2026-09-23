<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Products are created after sellers and categories in the platform migration.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // See the platform migration.
    }
};
