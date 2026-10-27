<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['return_pickup_photo_path', 'return_hub_photo_path'];

    /**
     * Proof of a returned item: the rider's photo when collecting it from the buyer, and the logistics partner's photo
     * when it arrives at the hub (App\Services\ReturnPickups).
     */
    public function up(): void
    {
        if (! Schema::hasTable('disputes')) {
            return;
        }
        Schema::table('disputes', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (! Schema::hasColumn('disputes', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('disputes')) {
            return;
        }
        $existing = array_values(array_filter(self::COLUMNS, fn ($column) => Schema::hasColumn('disputes', $column)));
        if ($existing) {
            Schema::table('disputes', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
