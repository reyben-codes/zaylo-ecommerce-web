<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sorting-center steps on a parcel (pickup confirmed for a date, sorted into a delivery area) and the
     * message threads between a logistics partner and its sellers and riders.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'pickup_confirmed_at')) {
                $table->timestamp('pickup_confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'pickup_scheduled_for')) {
                $table->date('pickup_scheduled_for')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'sorted_at')) {
                $table->timestamp('sorted_at')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'sort_area')) {
                $table->string('sort_area', 120)->nullable()->index();
            }
        });

        if (! Schema::hasTable('logistics_threads')) {
            Schema::create('logistics_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('logistics_provider_id')->constrained()->cascadeOnDelete();
                // The seller or rider on the other side of the conversation.
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
                $table->unique(['logistics_provider_id', 'user_id']);
                $table->index(['user_id', 'last_message_at']);
            });
        }

        if (! Schema::hasTable('logistics_messages')) {
            Schema::create('logistics_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('logistics_thread_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['logistics_thread_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_messages');
        Schema::dropIfExists('logistics_threads');
        $columns = array_values(array_filter(['pickup_confirmed_at', 'pickup_scheduled_for', 'sorted_at', 'sort_area'], fn ($column) => Schema::hasColumn('shipments', $column)));
        Schema::table('shipments', function (Blueprint $table) use ($columns) {
            if (Schema::hasIndex('shipments', ['sort_area'])) {
                $table->dropIndex(['sort_area']);
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
