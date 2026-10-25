<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What each rider did with each parcel: accepted or declined the assignment, reported a failed attempt (with
     * the reason), or delivered it. Works on both shipment schemas, and feeds the rider's reports and the
     * logistics partner's view of declines.
     */
    public function up(): void
    {
        if (Schema::hasTable('rider_delivery_logs')) {
            return;
        }

        Schema::create('rider_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('reason', 40)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['shipment_id', 'action']);
            $table->index(['user_id', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_delivery_logs');
    }
};
