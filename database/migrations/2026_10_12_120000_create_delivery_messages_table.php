<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat between the rider delivering a shipment and the buyer receiving it.
 * One thread per shipment; it accepts messages only while the delivery is in
 * progress, and stays readable afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            // True when the rider wrote it, false when the buyer did.
            $table->boolean('from_rider');
            // Set by the sender so a retried send is not stored twice.
            $table->uuid('client_id');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'client_id']);
            $table->index(['shipment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_messages');
    }
};
