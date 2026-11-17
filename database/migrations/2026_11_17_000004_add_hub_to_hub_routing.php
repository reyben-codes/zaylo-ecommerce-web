<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->json('delivery_location')->nullable());
        Schema::table('shipments', function (Blueprint $table) {
            foreach (['origin_hub_id', 'current_hub_id', 'destination_hub_id'] as $column) {
                $table->foreignId($column)->nullable()->constrained('logistics_providers')->restrictOnDelete();
            }
            $table->timestamp('hub_dispatched_at')->nullable();
            $table->timestamp('destination_received_at')->nullable();
            $table->foreignId('hub_dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('destination_received_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            foreach (['origin_hub_id', 'current_hub_id', 'destination_hub_id', 'hub_dispatched_by', 'destination_received_by'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['hub_dispatched_at', 'destination_received_at']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivery_location'));
    }
};
