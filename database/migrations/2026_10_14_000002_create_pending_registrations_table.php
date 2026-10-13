<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-ups waiting for their email code. The account is only created once the code is entered;
     * unfinished sign-ups expire after a day and never reserve an email or mobile number.
     */
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('email')->index();
            $table->string('role', 20);
            $table->longText('payload');
            $table->text('documents')->nullable();
            $table->string('code_hash')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('code_expires_at')->nullable();
            $table->timestamp('code_sent_at')->nullable();
            // dateTime, not timestamp: MySQL/MariaDB reject a required timestamp column without a default.
            $table->dateTime('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
