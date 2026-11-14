<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('refund_method', 30)->nullable();
            $table->string('refund_reference', 100)->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unique(['refunded_by', 'refund_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropUnique(['refunded_by', 'refund_reference']);
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn(['refund_method', 'refund_reference']);
        });
    }
};
