<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyers upload a government ID that an administrator approves before they can check out.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'identity_verified_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('identity_verified_at')->nullable());
            // Accounts that exist today are treated as verified; only new sign-ups upload an ID.
            DB::table('users')->update(['identity_verified_at' => now()]);
        }

        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('id_type', 40);
            $table->string('document_path');
            $table->string('original_name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
        if (Schema::hasColumn('users', 'identity_verified_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('identity_verified_at'));
        }
    }
};
