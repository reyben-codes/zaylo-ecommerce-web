<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DeliveryMessage;
use App\Models\LogisticsMessage;
use App\Models\LogisticsProvider;
use App\Models\LogisticsThread;
use App\Models\Message;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Every chat takes a photo or a video with a message: buyer–seller (website and app), Help & support (website, admin
 * and app), logistics chat, and the rider–buyer delivery chat. Only the people in a conversation can open its files.
 */
class ChatMediaTest extends TestCase
{
    use LegacyOrderTables;
    use RefreshDatabase;
    use SignsInMobile;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function video(string $name = 'unboxing.mp4', int $kilobytes = 900): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'video/mp4');
    }

    private function chat(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        return [$buyer, $seller, Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id])];
    }

    public function test_buyers_and_sellers_send_photos_and_videos_on_the_website_and_in_the_app(): void
    {
        [$buyer, $seller, $chat] = $this->chat();

        $this->actingAs($buyer)->get(route('buyer.chat', ['conversation' => $chat->id]))->assertOk()->assertSee('data-message-attach', false);

        // A video alone is a message, kept as sent on the private disk.
        $sent = $this->post(route('messages.send', $chat), ['client_id' => (string) Str::uuid(), 'video' => $this->video()], ['Accept' => 'application/json'])
            ->assertOk()->json('message');
        $message = Message::findOrFail($sent['id']);
        $this->assertSame('', $message->body);
        $this->assertStringStartsWith("chat/{$chat->id}/", $message->video_path);
        $this->assertStringEndsWith('.mp4', $message->video_path);
        Storage::disk('local')->assertExists($message->video_path);
        $this->assertSame(route('messages.media', [$chat->id, $message->id, 'video']), $sent['video']);
        $this->assertNull($sent['photo']);
        $this->get($sent['video'])->assertOk();

        // The seller sees it in the inbox and can open it; a photo with text works too.
        $this->actingAs($seller)->getJson(route('messages.inbox'))->assertJsonPath('conversations.0.preview', '🎥 Video');
        $this->get($sent['video'])->assertOk();
        $photo = $this->post(route('messages.send', $chat), [
            'body' => 'Here it is in black.', 'client_id' => (string) Str::uuid(), 'photo' => UploadedFile::fake()->image('black.jpg', 800, 600),
        ], ['Accept' => 'application/json'])->assertOk()->json('message');
        $this->assertSame('Here it is in black.', $photo['body']);
        $this->get($photo['photo'])->assertOk();

        // Someone outside the conversation cannot open its files.
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))->get($sent['video'])->assertNotFound();

        // The app gets links through its own routes, and sends files the same way.
        $this->signInMobile($buyer)->getJson("/api/mobile/messages/{$chat->id}")->assertOk()
            ->assertJsonPath('messages.0.video', route('mobile.messages.media', [$chat->id, $message->id, 'video']));
        $fromApp = $this->post("/api/mobile/messages/{$chat->id}", ['client_id' => (string) Str::uuid(), 'photo' => UploadedFile::fake()->image('size.png', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()->json('message');
        $this->assertStringContainsString('/api/mobile/messages/', $fromApp['photo']);
        $this->get($fromApp['photo'])->assertOk();
        $this->getJson("/api/mobile/messages")->assertJsonPath('conversations.0.preview', '📷 Photo');
    }

    public function test_attachments_are_checked(): void
    {
        [$buyer, , $chat] = $this->chat();
        $this->actingAs($buyer);
        $send = fn (array $data) => $this->post(route('messages.send', $chat), $data + ['client_id' => (string) Str::uuid()], ['Accept' => 'application/json']);

        $send([])->assertJsonValidationErrors(['body' => 'Type a message, or add a photo or video.']);
        $send(['video' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])->assertJsonValidationErrors(['video' => 'MP4, MOV, WebM or 3GP']);
        $send(['video' => $this->video(kilobytes: 26000)])->assertJsonValidationErrors(['video' => '25 MB or smaller']);
        $send(['photo' => UploadedFile::fake()->image('a.jpg'), 'video' => $this->video()])->assertJsonValidationErrors(['photo' => 'one photo or one video']);
        $this->assertSame(0, Message::count());

        // A retried upload with the same client id is stored, and saved to disk, once.
        $retry = ['client_id' => (string) Str::uuid(), 'video' => $this->video()];
        $this->post(route('messages.send', $chat), $retry, ['Accept' => 'application/json'])->assertOk();
        $this->post(route('messages.send', $chat), $retry + ['video' => $this->video()], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(1, Message::count());
        $this->assertCount(1, Storage::disk('local')->allFiles("chat/{$chat->id}"));
    }

    public function test_support_chat_takes_videos_on_the_website_and_photos_and_videos_in_the_app(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $thread = SupportThread::create(['user_id' => $buyer->id]);

        $sent = $this->actingAs($buyer)->post(route('support.send', $thread), ['body' => 'It arrived like this.', 'video' => $this->video()], ['Accept' => 'application/json'])
            ->assertCreated()->json('message');
        $this->assertSame(route('support.media', [$thread, $sent['id'], 'video']), $sent['video']);
        $this->get($sent['video'])->assertOk();
        $this->get(route('support'))->assertOk()->assertSee('<video', false)->assertSee('data-chat-attach', false);

        // The app now shows photos and videos instead of a note to open the website.
        $app = $this->signInMobile($buyer)->getJson('/api/mobile/support')->assertOk()->json('messages.0');
        $this->assertSame('It arrived like this.', $app['body']);
        $this->assertSame(route('mobile.support.media', [$sent['id'], 'video']), $app['video']);
        $this->get($app['video'])->assertOk();
        $photo = $this->post('/api/mobile/support/messages', ['client_id' => (string) Str::uuid(), 'photo' => UploadedFile::fake()->image('receipt.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->json('message');
        $this->get($photo['photo'])->assertOk();
        $this->assertSame('📷 Photo', SupportMessage::findOrFail($photo['id'])->preview);

        // Another account cannot open the thread's files, in the app or on the website.
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($other)->get($app['video'])->assertNotFound();
        $this->actingAs($other)->get($sent['video'])->assertForbidden();
    }

    public function test_logistics_chat_takes_photos_and_videos(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express', 'slug' => 'express', 'status' => 'approved']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $thread = LogisticsThread::create(['logistics_provider_id' => $provider->id, 'user_id' => $seller->id]);

        $sent = $this->actingAs($owner)->post(route('logistics-chat.send', $thread), ['video' => $this->video('dock.mov', 500)], ['Accept' => 'application/json'])
            ->assertCreated()->json('message');
        $this->assertSame(route('logistics-chat.media', [$thread, $sent['id'], 'video']), $sent['video']);
        $this->assertSame('🎥 Video', LogisticsMessage::findOrFail($sent['id'])->preview);
        $this->get(route('logistics.messages', ['thread' => $thread->id]))->assertOk()->assertSee('data-chat-attach', false)->assertSee('<video', false);

        $this->actingAs($seller)->get($sent['video'])->assertOk();
        $this->post(route('logistics-chat.send', $thread), ['photo' => UploadedFile::fake()->image('label.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs(User::factory()->create(['role' => 'seller', 'status' => 'active']))->get($sent['video'])->assertForbidden();
    }

    public function test_riders_and_buyers_send_photos_and_videos_in_the_delivery_chat(): void
    {
        $this->useLegacyOrderTables();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $rider = $this->approvedRider();
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-MEDIA-1', 'user_id' => $buyer->id, 'status' => 'shipped', 'subtotal' => 500, 'shipping_fee' => 49,
            'total' => 549, 'payment_method' => 'cod', 'recipient_name' => 'Maria Santos', 'phone' => '09170000000',
            'shipping_address' => '12 Rizal St', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $shipmentId = DB::table('shipments')->insertGetId([
            'order_id' => $orderId, 'courier_id' => $rider->id, 'tracking_number' => 'TRK-MEDIA', 'status' => 'out_for_delivery',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The rider shows the gate they are at.
        $sent = $this->signInMobile($rider)->post("/api/mobile/rider/deliveries/{$shipmentId}/chat", [
            'client_id' => (string) Str::uuid(), 'photo' => UploadedFile::fake()->image('gate.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('message');
        $this->assertSame(route('mobile.rider.deliveries.chat.media', [$shipmentId, $sent['id'], 'photo']), $sent['photo']);
        $this->get($sent['photo'])->assertOk();

        // The buyer opens it through the order, and answers with a video.
        $chat = $this->signInMobile($buyer)->getJson("/api/mobile/orders/{$orderId}/chat")->assertOk()->json('chat');
        $this->assertSame(route('mobile.orders.chat.media', [$orderId, $sent['id'], 'photo']), $chat['messages'][0]['photo']);
        $this->get($chat['messages'][0]['photo'])->assertOk();
        $video = $this->post("/api/mobile/orders/{$orderId}/chat", ['client_id' => (string) Str::uuid(), 'video' => $this->video()], ['Accept' => 'application/json'])
            ->assertCreated()->json('message');
        $this->assertNotNull(DeliveryMessage::findOrFail($video['id'])->video_path);
        $this->get($video['video'])->assertOk();
    }

    public function test_videos_on_private_r2_storage_use_short_lived_signed_links(): void
    {
        [$buyer, , $chat] = $this->chat();
        config(['filesystems.disks.local.driver' => 's3']);
        Storage::disk('local')->buildTemporaryUrlsUsing(fn (string $path, $expires) => "https://r2.example/{$path}?expires={$expires->getTimestamp()}");

        $sent = $this->actingAs($buyer)->post(route('messages.send', $chat), ['client_id' => (string) Str::uuid(), 'video' => $this->video()], ['Accept' => 'application/json'])
            ->assertOk()->json('message');
        $path = Message::findOrFail($sent['id'])->video_path;

        // Phones and browsers stream the video straight from storage, so it can be skipped through.
        $this->assertStringStartsWith("https://r2.example/{$path}?expires=", $sent['video']);
        $this->get(route('messages.media', [$chat->id, $sent['id'], 'video']))->assertRedirectContains("https://r2.example/{$path}");
    }
}
