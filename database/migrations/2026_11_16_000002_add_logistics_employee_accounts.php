<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restrict deletion: losing this link must never turn an employee into an owner.
            $table->foreignId('logistics_employee_provider_id')->nullable()->constrained('logistics_providers')->restrictOnDelete();
            $table->string('logistics_position', 40)->nullable();
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        // Do not turn existing restricted accounts into unrestricted logistics owners on rollback.
        if (\Illuminate\Support\Facades\DB::table('users')->whereNotNull('logistics_employee_provider_id')->exists()) {
            throw new RuntimeException('Employee accounts exist; retain their permission columns.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logistics_employee_provider_id');
            $table->dropColumn(['logistics_position', 'must_change_password']);
        });
    }
};
