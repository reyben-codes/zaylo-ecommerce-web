<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One support chat for every role. The rider app used its own
 * support_conversations table and a support_messages table with different
 * columns; databases that ran that version have their rider messages moved
 * into support_threads, which the Admin Center answers from.
 *
 * Also adds support_messages.client_id: the rider app sends one with each
 * message so a retried send is stored once. Website messages leave it empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_conversations') && Schema::hasColumn('support_messages', 'support_conversation_id')) {
            $this->moveRiderConversations();
        }

        if (! Schema::hasColumn('support_messages', 'client_id')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->uuid('client_id')->nullable()->after('sender_id');
                $table->unique(['support_thread_id', 'client_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_messages', 'client_id')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->dropUnique(['support_thread_id', 'client_id']);
                $table->dropColumn('client_id');
            });
        }
    }

    private function moveRiderConversations(): void
    {
        Schema::rename('support_messages', 'rider_support_messages_old');

        if (! Schema::hasTable('support_threads')) {
            Schema::create('support_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamps();
            });
        }
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('client_id')->nullable();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['support_thread_id', 'id']);
            $table->unique(['support_thread_id', 'client_id']);
        });

        DB::transaction(function () {
            foreach (DB::table('support_conversations')->orderBy('id')->get() as $conversation) {
                $thread = DB::table('support_threads')->where('user_id', $conversation->user_id)->first();
                $threadId = $thread?->id ?? DB::table('support_threads')->insertGetId([
                    'user_id' => $conversation->user_id,
                    'last_message_at' => $conversation->last_message_at,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                ]);

                $messages = DB::table('rider_support_messages_old')
                    ->where('support_conversation_id', $conversation->id)
                    // The new table requires a sender; messages from deleted staff accounts cannot be kept.
                    ->whereNotNull('sender_id')
                    ->orderBy('id')
                    ->get();
                foreach ($messages as $message) {
                    DB::table('support_messages')->insert([
                        'support_thread_id' => $threadId,
                        'sender_id' => $message->sender_id,
                        'client_id' => $message->client_id,
                        'body' => $message->body,
                        'read_at' => $message->read_at,
                        'created_at' => $message->created_at,
                        'updated_at' => $message->updated_at,
                    ]);
                }
                if ($conversation->last_message_at) {
                    DB::table('support_threads')->where('id', $threadId)
                        ->where(fn ($query) => $query->whereNull('last_message_at')->orWhere('last_message_at', '<', $conversation->last_message_at))
                        ->update(['last_message_at' => $conversation->last_message_at]);
                }
            }
        });

        Schema::drop('rider_support_messages_old');
        Schema::drop('support_conversations');
    }
};
