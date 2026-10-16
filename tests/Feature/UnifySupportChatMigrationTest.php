<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Databases that ran the rider app's own support chat (support_conversations
 * plus a support_messages table with different columns) are moved onto the
 * shared support_threads.
 */
class UnifySupportChatMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_app_conversations_move_into_support_threads(): void
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $buyer = User::factory()->create(['role' => 'buyer']);
        $admin = User::factory()->create(['role' => 'admin']);

        // The rider app's tables, as an earlier version created them.
        Schema::dropIfExists('support_messages');
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_conversation_id');
            $table->foreignId('sender_id')->nullable();
            $table->boolean('from_staff')->default(false);
            $table->uuid('client_id');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        // The buyer already has a website thread; the rider does not.
        $buyerThread = DB::table('support_threads')->insertGetId(['user_id' => $buyer->id, 'created_at' => now(), 'updated_at' => now()]);
        $riderConversation = DB::table('support_conversations')->insertGetId(['user_id' => $rider->id, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $buyerConversation = DB::table('support_conversations')->insertGetId(['user_id' => $buyer->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('support_messages')->insert([
            ['support_conversation_id' => $riderConversation, 'sender_id' => $rider->id, 'from_staff' => false, 'client_id' => '11111111-1111-4111-8111-111111111111', 'body' => 'The seller is closed.', 'read_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['support_conversation_id' => $riderConversation, 'sender_id' => $admin->id, 'from_staff' => true, 'client_id' => '22222222-2222-4222-8222-222222222222', 'body' => 'We are calling them.', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['support_conversation_id' => $riderConversation, 'sender_id' => null, 'from_staff' => true, 'client_id' => '33333333-3333-4333-8333-333333333333', 'body' => 'From a deleted staff account.', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['support_conversation_id' => $buyerConversation, 'sender_id' => $buyer->id, 'from_staff' => false, 'client_id' => '44444444-4444-4444-8444-444444444444', 'body' => 'Hello', 'read_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        (require database_path('migrations/2026_10_16_000002_unify_support_chat.php'))->up();

        $this->assertFalse(Schema::hasTable('support_conversations'));
        $this->assertTrue(Schema::hasColumns('support_messages', ['support_thread_id', 'client_id']));
        $riderThread = DB::table('support_threads')->where('user_id', $rider->id)->sole();
        $this->assertSame(
            ['The seller is closed.', 'We are calling them.'],
            DB::table('support_messages')->where('support_thread_id', $riderThread->id)->orderBy('id')->pluck('body')->all(),
        );
        // Existing website threads are reused rather than duplicated.
        $this->assertSame(1, DB::table('support_threads')->where('user_id', $buyer->id)->count());
        $this->assertSame(1, DB::table('support_messages')->where('support_thread_id', $buyerThread)->count());
    }
}
