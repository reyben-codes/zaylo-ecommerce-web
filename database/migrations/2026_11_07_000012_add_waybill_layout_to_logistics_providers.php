<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each logistics partner prints its own waybill design (App\Support\Waybill::LAYOUTS), chosen on its Account page.
 * Empty means the default for that partner (J&T or Flash by name, otherwise one other partners do not use).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('logistics_providers') && ! Schema::hasColumn('logistics_providers', 'waybill_layout')) {
            Schema::table('logistics_providers', function (Blueprint $table) {
                $table->string('waybill_layout', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('logistics_providers', 'waybill_layout')) {
            Schema::table('logistics_providers', fn (Blueprint $table) => $table->dropColumn('waybill_layout'));
        }
    }
};
