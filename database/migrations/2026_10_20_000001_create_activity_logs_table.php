<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What each account did: sign-ins, account changes, orders, products, parcels and admin decisions.
        // The actor's name, role and the subject's label are copied in so entries stay readable after a deletion.
        // logistics_provider_id groups a logistics partner's owner and riders so the partner sees its whole team.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 30)->nullable()->index();
            $table->unsignedBigInteger('logistics_provider_id')->nullable()->index();
            $table->string('event', 80)->index();
            $table->string('category', 30)->index();
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->string('subject_label')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
