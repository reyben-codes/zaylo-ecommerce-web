<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COD cash handed over by riders: the cash a rider collected on delivery is counted at the hub, and any shortfall is
 * kept against the rider. Each delivered COD parcel points at the handover that settled it, so it is counted once
 * (App\Services\CashHandovers).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rider_cash_handovers')) {
            Schema::create('rider_cash_handovers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('logistics_provider_id')->nullable();
                // The rider's user account (parcels name riders through it on either schema).
                $table->foreignId('rider_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('parcels');
                $table->decimal('expected', 12, 2);
                $table->decimal('received', 12, 2);
                $table->decimal('shortfall', 12, 2)->default(0);
                $table->string('note', 255)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['logistics_provider_id', 'created_at']);
            });
        }

        if (Schema::hasTable('shipments') && ! Schema::hasColumn('shipments', 'cash_handover_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->unsignedBigInteger('cash_handover_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'cash_handover_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                // The index goes first: SQLite cannot drop a column that is still indexed.
                $table->dropIndex(['cash_handover_id']);
                $table->dropColumn('cash_handover_id');
            });
        }
        Schema::dropIfExists('rider_cash_handovers');
    }
};
