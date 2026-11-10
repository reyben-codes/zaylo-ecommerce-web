<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        if (! Schema::hasColumn('shipments', 'cod_amount_minor')) {
            Schema::table('shipments', fn (Blueprint $table) => $table->unsignedInteger('cod_amount_minor')->default(0));
        }
        if (! Schema::hasColumn('shipments', 'cod_collected')) {
            Schema::table('shipments', fn (Blueprint $table) => $table->boolean('cod_collected')->default(false));
        }
        if (! Schema::hasColumn('shipments', 'cod_collected_at')) {
            Schema::table('shipments', fn (Blueprint $table) => $table->timestamp('cod_collected_at')->nullable());
        }
        if (! Schema::hasColumn('shipments', 'cod_collected_by')) {
            Schema::table('shipments', fn (Blueprint $table) => $table->foreignId('cod_collected_by')->nullable()->constrained('users')->nullOnDelete());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }
        if (Schema::hasColumn('shipments', 'cod_collected_by')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropForeign(['cod_collected_by']);
                $table->dropColumn('cod_collected_by');
            });
        }
        foreach (['cod_collected_at', 'cod_collected', 'cod_amount_minor'] as $column) {
            if (Schema::hasColumn('shipments', $column)) {
                Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
