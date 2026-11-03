<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each rider's work schedule, set by their logistics partner, and the time in / time out the rider records by
 * scanning the attendance QR code shown at the partner's hub.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->json('work_days')->nullable();
        });

        Schema::create('rider_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logistics_provider_id')->nullable()->constrained()->nullOnDelete();
            // The Manila date the shift started on; a night shift timed out after midnight keeps it.
            $table->date('work_date');
            $table->dateTime('time_in_at');
            $table->dateTime('time_out_at')->nullable();
            // The schedule when the rider timed in, so later schedule changes do not rewrite past days.
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->timestamps();

            $table->unique(['rider_id', 'work_date']);
            $table->index(['logistics_provider_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_attendances');
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['shift_start', 'shift_end', 'work_days']);
        });
    }
};
