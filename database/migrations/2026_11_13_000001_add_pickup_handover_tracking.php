<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('pickup_scanned_at')->nullable();
            $table->timestamp('hub_received_at')->nullable();
            $table->foreignId('hub_received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('hub_receipt_checks')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'hub_received_by')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropForeign(['hub_received_by']);
                $table->dropColumn(['pickup_scanned_at', 'hub_received_at', 'hub_received_by', 'hub_receipt_checks']);
            });
        }
    }
};
