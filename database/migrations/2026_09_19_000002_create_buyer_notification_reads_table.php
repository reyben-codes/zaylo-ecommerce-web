<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_notification_reads', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_key', 100);
            $table->timestamp('read_at');
            $table->primary(['user_id', 'notification_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_notification_reads');
    }
};
