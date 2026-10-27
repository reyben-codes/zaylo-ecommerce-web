<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['return_rider_id', 'return_assigned_at', 'return_picked_up_at', 'return_at_hub_at'];

    /**
     * A returned item's pickup: the logistics partner assigns one of its riders, who scans the return label at the
     * buyer's door and brings the item to the hub (App\Services\ReturnPickups).
     */
    public function up(): void
    {
        if (! Schema::hasTable('disputes')) {
            return;
        }
        Schema::table('disputes', function (Blueprint $table) {
            if (! Schema::hasColumn('disputes', 'return_rider_id')) {
                // The rider's user account, the same on both schemas.
                $table->unsignedBigInteger('return_rider_id')->nullable()->index();
            }
            foreach (['return_assigned_at', 'return_picked_up_at', 'return_at_hub_at'] as $column) {
                if (! Schema::hasColumn('disputes', $column)) {
                    $table->timestamp($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('disputes')) {
            return;
        }
        // The index goes first: SQLite cannot drop a column that an index still uses.
        if (Schema::hasIndex('disputes', 'disputes_return_rider_id_index')) {
            Schema::table('disputes', fn (Blueprint $table) => $table->dropIndex('disputes_return_rider_id_index'));
        }
        $existing = array_values(array_filter(self::COLUMNS, fn ($column) => Schema::hasColumn('disputes', $column)));
        if ($existing) {
            Schema::table('disputes', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
