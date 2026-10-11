<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A rider application is saved only when the rider submits it from the Terms
 * step, so it always records when (and to which version) the terms were
 * accepted. The database now enforces that, along with the plate number.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('rider_applications')
            ->where(fn ($query) => $query->whereNull('terms_accepted_at')->orWhereNull('terms_version')->orWhereNull('plate_number'))
            ->pluck('id');

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(
                'Rider applications '.$missing->implode(', ').' have no accepted terms or plate number. '
                .'Remove or complete them before running this migration.'
            );
        }

        Schema::table('rider_applications', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable(false)->change();
            $table->string('terms_version', 20)->nullable(false)->change();
            $table->string('plate_number', 6)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->change();
            $table->string('terms_version', 20)->nullable()->change();
            $table->string('plate_number', 6)->nullable()->change();
        });
    }
};
