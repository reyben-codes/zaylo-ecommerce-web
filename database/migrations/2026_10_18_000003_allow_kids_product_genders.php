<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products can be for girls, boys or kids as well as women, men or unisex. The deployed database already stores
     * this as text; the newer schema's column only allowed men/women/unisex, so it becomes a short text column.
     */
    public function up(): void
    {
        $limited = DB::getDriverName() === 'sqlite' || Schema::getColumnType('products', 'gender') === 'enum';
        if ($limited) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('gender', 20)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Kids values may now be stored, so the column is left as text.
    }
};
