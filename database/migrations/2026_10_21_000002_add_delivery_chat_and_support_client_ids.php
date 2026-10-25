<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The rider–buyer chat in the apps: one thread per shipment. It accepts messages only while the delivery is
        // in progress, and stays readable afterwards (see MobileDeliveryChatController).
        if (! Schema::hasTable('delivery_messages')) {
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

        // The apps send a client id with each support message so a retried send is stored once.
        // Messages sent from the website leave it empty.
        if (Schema::hasTable('support_messages') && ! Schema::hasColumn('support_messages', 'client_id')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->uuid('client_id')->nullable()->after('sender_id');
                $table->unique(['support_thread_id', 'client_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_messages', 'client_id')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->dropUnique(['support_thread_id', 'client_id']);
                $table->dropColumn('client_id');
            });
        }
        Schema::dropIfExists('delivery_messages');
    }
};
