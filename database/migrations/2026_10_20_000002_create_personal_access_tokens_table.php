<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sign-in tokens for the mobile app (App\Models\MobileToken). Same table and token format as Laravel Sanctum,
        // so the app keeps working if the project later switches to Sanctum.
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        // Riders give their gender when they sign up in the app.
        if (! Schema::hasColumn('users', 'gender')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('gender', 20)->nullable()->after('date_of_birth');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        if (Schema::hasColumn('users', 'gender')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('gender'));
        }
    }
};
