<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create([
            'seller_id' => $seller->id, 'name' => 'Chat test product', 'category' => 'shoes',
            'price' => 100, 'stock' => 5, 'is_active' => true,
        ]);

        return [$buyer, $seller, $product];
    }

    public function test_buyer_starts_one_conversation_with_the_real_product_owner_and_both_can_reply(): void
    {
        [$buyer, $seller, $product] = $this->accounts();
        $this->actingAs($buyer)->get(route('products.show', $product))->assertOk()->assertSee('Chat with seller');
        $this->post(route('buyer.chat.start', $product))->assertRedirect();
        $this->post(route('buyer.chat.start', $product))->assertRedirect();
        $this->assertDatabaseCount('conversations', 1);
        $chat = Conversation::firstOrFail();
        $this->assertEquals($seller->id, $chat->seller_id);
        $this->assertEquals($buyer->id, $chat->buyer_id);
        $this->get(route('buyer.chat', ['conversation' => $chat->id]))->assertOk()->assertSee('Chat test product');
        $payload = ['body' => 'Is this available?', 'client_id' => (string) Str::uuid(), 'sender_id' => $seller->id];
        $this->postJson(route('messages.send', $chat), $payload)->assertOk()->assertJsonPath('message.mine', true);
        $this->postJson(route('messages.send', $chat), $payload)->assertOk();
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('messages', ['sender_id' => $buyer->id, 'body' => 'Is this available?']);
        $this->assertNotNull($chat->fresh()->last_message_at);

        $this->actingAs($seller)->get(route('seller.chat', ['conversation' => $chat->id]))
            ->assertOk()->assertSee('seller-site-header')->assertSee($buyer->name);
        $this->getJson(route('messages.inbox'))->assertOk()->assertJsonPath('conversations.0.unread', 1);
        $this->getJson(route('messages.list', $chat))->assertJsonPath('messages.0.mine', false);
        $this->assertNull($chat->messages()->first()->read_at); // GET does not mark unseen messages read.
        $this->postJson(route('messages.read', $chat), ['through' => $chat->messages()->first()->id])->assertNoContent();
        $this->getJson(route('messages.inbox'))->assertJsonPath('conversations.0.unread', 0);
        $this->postJson(route('messages.send', $chat), ['body' => 'Yes, it is!', 'client_id' => (string) Str::uuid()])->assertOk();
        $this->actingAs($buyer)->getJson(route('messages.inbox'))->assertJsonPath('conversations.0.unread', 1);
        $this->getJson(route('messages.list', $chat))->assertJsonCount(2, 'messages')->assertJsonPath('messages.1.body', 'Yes, it is!');
    }

    public function test_outsiders_cannot_list_send_or_mark_messages_read(): void
    {
        [$buyer, $seller] = $this->accounts();
        $chat = Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        $message = $chat->messages()->create(['sender_id' => $buyer->id, 'body' => 'Private message', 'client_id' => (string) Str::uuid()]);
        foreach (['buyer', 'seller'] as $role) {
            $outsider = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($outsider)->getJson(route('messages.inbox'))->assertJsonCount(0, 'conversations');
            $this->get(route($role.'.chat', ['conversation' => $chat->id]))->assertNotFound();
            $this->getJson(route('messages.list', $chat))->assertNotFound();
            $this->postJson(route('messages.send', $chat), ['body' => 'Intrusion', 'client_id' => (string) Str::uuid()])->assertNotFound();
            $this->postJson(route('messages.read', $chat), ['through' => $message->id])->assertNotFound();
        }
        $this->assertNull($message->fresh()->read_at);
        $this->assertDatabaseCount('messages', 1);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->getJson(route('messages.list', $chat))->assertForbidden();
    }

    public function test_message_validation_and_unavailable_sellers(): void
    {
        [$buyer, $seller, $product] = $this->accounts();
        $chat = Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        $this->actingAs($buyer);
        foreach (['   ', str_repeat('a', 2001), ['invalid']] as $body) {
            $this->postJson(route('messages.send', $chat), ['body' => $body, 'client_id' => (string) Str::uuid()])
                ->assertUnprocessable()->assertJsonValidationErrors('body');
        }
        $this->postJson(route('messages.send', $chat), ['body' => 'Hello', 'client_id' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('client_id');
        $seller->update(['status' => 'suspended']);
        $this->post(route('buyer.chat.start', $product))->assertNotFound();
        $this->postJson(route('messages.send', $chat), ['body' => 'Hello', 'client_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_history_pagination_and_read_watermarks_do_not_drop_or_read_future_messages(): void
    {
        [$buyer, $seller] = $this->accounts();
        $chat = Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        foreach (range(1, 55) as $i) {
            $chat->messages()->create(['sender_id' => $seller->id, 'body' => 'Message '.$i, 'client_id' => (string) Str::uuid()]);
        }
        $this->actingAs($buyer)->getJson(route('messages.list', $chat))
            ->assertOk()->assertJsonCount(50, 'messages')->assertJsonPath('messages.0.body', 'Message 6')->assertJsonPath('has_older', true);
        $sixth = $chat->messages()->orderBy('id')->skip(5)->first()->id;
        $this->getJson(route('messages.list', $chat).'?before='.$sixth)
            ->assertJsonCount(5, 'messages')->assertJsonPath('messages.0.body', 'Message 1')->assertJsonPath('has_older', false);
        $this->getJson(route('messages.list', $chat).'?after='.$sixth)->assertJsonCount(49, 'messages');
        $this->postJson(route('messages.read', $chat), ['through' => $sixth])->assertNoContent();
        $this->assertEquals(49, $chat->messages()->whereNull('read_at')->count());
    }

    public function test_guests_and_unverified_accounts_cannot_access_messaging(): void
    {
        $this->getJson(route('messages.inbox'))->assertUnauthorized();
        $buyer = User::factory()->unverified()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->getJson(route('messages.inbox'))->assertForbidden();
    }

    public function test_compact_chat_reuses_private_conversations_without_nesting_a_widget(): void
    {
        [$buyer, $seller, $product] = $this->accounts();
        $chat = Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_id' => $product->id]);
        foreach ([$buyer, $seller] as $user) {
            $this->actingAs($user)->get(route($user->role.'.chat'))
                ->assertOk()->assertSee('data-chat-widget', false)->assertSee('data-chat-drop-target', false);
            $this->get(route($user->role.'.chat', ['compact' => 1]))
                ->assertOk()->assertSee('messaging-compact')->assertDontSee('data-chat-widget', false);
            $this->get(route($user->role.'.chat', ['compact' => 1, 'conversation' => $chat->id]))
                ->assertOk()->assertSee('data-compact="true"', false)->assertSee('All conversations')->assertSee($product->name)
                ->assertDontSee('data-chat-widget', false);
        }
        $outsider = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($outsider)->get(route('buyer.chat', ['compact' => 1, 'conversation' => $chat->id]))->assertNotFound();
        $this->actingAs($buyer)->getJson(route('buyer.chat', ['compact' => 'invalid']))->assertUnprocessable();
    }

    public function test_storefront_bubble_is_only_available_to_verified_active_accounts(): void
    {
        [$buyer, $seller, $product] = $this->accounts();
        $this->get(route('products.show', $product))->assertOk()->assertDontSee('data-chat-widget', false);
        $this->actingAs($buyer)->get(route('products.show', $product))->assertOk()->assertSee('data-chat-widget', false);
        $this->get(route('home'))->assertOk()->assertSee('data-chat-widget', false);
        $buyer->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($buyer->fresh())->get(route('products.show', $product))->assertOk()->assertDontSee('data-chat-widget', false);
    }
}
