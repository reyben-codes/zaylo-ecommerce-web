<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sellers without a printer: the logistics partner prints the waybill. The seller writes a daily queue number and
 * colour (e.g. "07 BLUE") on the parcel, and the printed waybill carries the same mark, so the right label goes on the
 * right parcel at pickup (App\Support\WaybillQueue).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments') || Schema::hasColumn('shipments', 'partner_prints')) {
            return;
        }
        Schema::table('shipments', function (Blueprint $table) {
            $table->boolean('partner_prints')->default(false);
            $table->unsignedSmallInteger('queue_number')->nullable();
            $table->string('queue_color', 20)->nullable();
            $table->date('queue_date')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'partner_prints')) {
            Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn(['partner_prints', 'queue_number', 'queue_color', 'queue_date']));
        }
    }
};
