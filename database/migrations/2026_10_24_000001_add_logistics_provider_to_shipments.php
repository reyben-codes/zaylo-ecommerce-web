<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which logistics partner handles each parcel, on the deployed schema (the newer schema already has it).
     * The seller picks the partner when marking an order ready to ship; partners only see their own parcels.
     * Existing parcels go to the only approved partner when there is exactly one, so nothing disappears
     * from that partner's screens.
     */
    public function up(): void
    {
        if (! Schema::hasTable('shipments') || Schema::hasColumn('shipments', 'logistics_provider_id')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('logistics_provider_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });

        $approved = DB::table('logistics_providers')->where('status', 'approved')->pluck('id');
        if ($approved->count() === 1) {
            DB::table('shipments')->whereNull('logistics_provider_id')->update(['logistics_provider_id' => $approved->first()]);
        }
    }

    /** Only the deployed schema got the column here; the newer schema (with seller orders) had it from the start. */
    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'logistics_provider_id') && ! Schema::hasColumn('shipments', 'seller_order_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('logistics_provider_id');
            });
        }
    }
};
