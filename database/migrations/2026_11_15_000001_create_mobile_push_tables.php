<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_messages', function (Blueprint $table) {
            $table->uuid('client_id')->nullable();
            $table->unique(['logistics_thread_id', 'client_id']);
        });
        Schema::create('mobile_push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_token_id')->unique()->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->text('token'); // Encrypted at rest; never returned by the API or logged.
            $table->string('token_hash', 64)->index();
            $table->string('platform', 10)->default('android');
            $table->timestamps();
        });
        Schema::create('mobile_push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('mobile_push_devices')->cascadeOnDelete();
            $table->string('event_key', 190);
            $table->string('title', 100);
            $table->string('body', 250);
            $table->json('data');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_push_messages');
        Schema::dropIfExists('mobile_push_devices');
        Schema::table('logistics_messages', function (Blueprint $table) {
            $table->dropUnique(['logistics_thread_id', 'client_id']);
            $table->dropColumn('client_id');
        });
    }
};
