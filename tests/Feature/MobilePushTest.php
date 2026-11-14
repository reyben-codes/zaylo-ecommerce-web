<?php

namespace Tests\Feature;

use App\Models\MobilePushDevice;
use App\Models\MobilePushMessage;
use App\Models\MobileToken;
use App\Models\User;
use App\Services\FirebaseMessaging;
use App\Services\MobilePushNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class MobilePushTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    private function device(User $user, string $fid = 'fixture-installation-1234'): MobilePushDevice
    {
        $bearer = $user->createToken('Phone');
        return MobilePushDevice::create([
            'user_id' => $user->id, 'mobile_token_id' => (int) explode('|', $bearer)[0],
            'token' => $fid, 'token_hash' => hash('sha256', $fid), 'platform' => 'android',
        ]);
    }

    public function test_registration_requires_auth_and_stores_an_encrypted_device_for_this_session(): void
    {
        $this->putJson('/api/mobile/push-device', ['token' => 'fixture-installation-1234', 'platform' => 'android'])->assertUnauthorized();
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($user)->putJson('/api/mobile/push-device', ['token' => 'fixture-installation-1234', 'platform' => 'android'])
            ->assertOk()->assertDontSee('fixture-installation-1234');
        $device = MobilePushDevice::sole();
        $this->assertSame('fixture-installation-1234', $device->token);
        $this->assertNotSame($device->token, DB::table('mobile_push_devices')->value('token'));
        $this->putJson('/api/mobile/push-device', ['token' => 'replacement-installation-1234', 'platform' => 'android'])->assertOk();
        $this->assertDatabaseCount('mobile_push_devices', 1);
        $this->assertSame('replacement-installation-1234', $device->fresh()->token);
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($other)->deleteJson('/api/mobile/push-device')->assertOk();
        $this->assertDatabaseCount('mobile_push_devices', 1);
    }

    public function test_logout_and_password_revocation_remove_devices_and_queued_messages(): void
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($user)->putJson('/api/mobile/push-device', ['token' => 'fixture-installation-1234', 'platform' => 'android'])->assertOk();
        app(MobilePushNotifications::class)->queue($user->id, 'one', 'Update', 'Open ZAYLO.', 'order', 10);
        $this->assertDatabaseCount('mobile_push_messages', 1);
        $this->postJson('/api/mobile/logout')->assertOk();
        $this->assertDatabaseCount('mobile_push_devices', 0);
        $this->assertDatabaseCount('mobile_push_messages', 0);
        $this->device($user);
        app(\App\Services\AccountSessions::class)->revokeMobileTokens($user);
        $this->assertDatabaseCount('mobile_push_devices', 0);
    }

    public function test_expired_or_suspended_sessions_receive_no_push(): void
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $device = $this->device($user);
        MobileToken::find($device->mobile_token_id)->update(['expires_at' => now()->subMinute()]);
        app(MobilePushNotifications::class)->queue($user->id, 'one', 'Update', 'Open ZAYLO.', 'order', 10);
        $this->assertDatabaseCount('mobile_push_messages', 0);
        $this->device($user, 'another-installation-1234');
        $user->update(['status' => 'suspended']);
        app(MobilePushNotifications::class)->queue($user->id, 'two', 'Update', 'Open ZAYLO.', 'order', 10);
        $this->assertDatabaseCount('mobile_push_messages', 0);
    }

    public function test_test_notification_targets_only_the_requesting_phone(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $other = $this->device($buyer, 'other-phone-installation-1234');
        $this->signInMobile($buyer)->postJson('/api/mobile/push-device/test')->assertUnprocessable();
        $this->putJson('/api/mobile/push-device', ['token' => 'current-installation-1234', 'platform' => 'android'])->assertOk();
        $this->postJson('/api/mobile/push-device/test', ['user_id' => 999])->assertAccepted();
        $this->assertDatabaseCount('mobile_push_messages', 1);
        $this->assertNotSame($other->id, MobilePushMessage::sole()->device_id);
        $this->assertSame((string) $buyer->id, MobilePushMessage::sole()->data['user_id']);
    }

    public function test_unrelated_parcel_updates_do_not_repeat_the_assignment_notification(): void
    {
        $rider = $this->approvedRider();
        $this->device($rider);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'reference' => 'PUSH-ORDER', 'total_minor' => 10000, 'payment_method' => 'cod', 'shipping_address' => []]);
        $sellerOrder = $order->sellerOrders()->create(['seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'ready_to_ship']);
        $shipment = $sellerOrder->shipment()->create(['logistics_provider_id' => $rider->rider->logistics_provider_id, 'rider_id' => $rider->rider->id, 'tracking_code' => 'PUSH-TRACK', 'status' => 'assigned']);
        $this->assertDatabaseCount('mobile_push_messages', 1);
        $shipment->update(['cod_amount_minor' => 10000]);
        $this->assertDatabaseCount('mobile_push_messages', 1);
    }

    public function test_events_are_transactional_deduplicated_and_retried_without_duplicate_success(): void
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->device($user);
        $push = app(MobilePushNotifications::class);
        try {
            DB::transaction(function () use ($push, $user) {
                $push->queue($user->id, 'rolled-back', 'Update', 'Open ZAYLO.', 'order', 10);
                throw new \RuntimeException('Rollback fixture');
            });
        } catch (\RuntimeException) {}
        $this->assertDatabaseCount('mobile_push_messages', 0);
        $push->queue($user->id, 'one', 'Update', 'Open ZAYLO.', 'order', 10);
        $push->queue($user->id, 'one', 'Update', 'Open ZAYLO.', 'order', 10);
        $this->assertDatabaseCount('mobile_push_messages', 1);
        $firebase = $this->mock(FirebaseMessaging::class);
        $firebase->shouldReceive('configured')->andReturnTrue();
        $firebase->shouldReceive('send')->once()->andReturn('retry');
        $this->assertSame(0, $push->drain());
        $this->assertSame(1, MobilePushMessage::sole()->attempts);
        $this->assertSame(0, $push->drain());
        $this->travel(3)->minutes();
        $firebase->shouldReceive('send')->once()->andReturn('sent');
        $this->assertSame(1, $push->drain());
        $this->assertSame(0, $push->drain());
        $this->assertNotNull(MobilePushMessage::sole()->sent_at);
    }

    public function test_unregistered_installation_is_removed_but_concurrent_refresh_is_preserved(): void
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $device = $this->device($user);
        $push = app(MobilePushNotifications::class);
        $push->queue($user->id, 'one', 'Update', 'Open ZAYLO.', 'order', 10);
        $firebase = $this->mock(FirebaseMessaging::class);
        $firebase->shouldReceive('configured')->andReturnTrue();
        $firebase->shouldReceive('send')->once()->andReturnUsing(function () use ($device) {
            $device->update(['token' => 'refreshed-installation-1234', 'token_hash' => hash('sha256', 'refreshed-installation-1234')]);
            return 'unregistered';
        });
        $push->drain();
        $this->assertSame('refreshed-installation-1234', $device->fresh()->token);
        $this->travel(4)->minutes();
        $firebase->shouldReceive('send')->once()->andReturn('unregistered');
        $push->drain();
        $this->assertDatabaseCount('mobile_push_devices', 0);
    }

    public function test_seller_message_targets_only_the_buyer_with_a_generic_preview(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $device = $this->device($buyer);
        $this->device($other, 'unrelated-installation-1234');
        $thread = \App\Models\Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        $thread->messages()->create(['sender_id' => $seller->id, 'client_id' => (string) \Illuminate\Support\Str::uuid(), 'body' => 'Private message text']);
        $this->assertDatabaseCount('mobile_push_messages', 1);
        $message = MobilePushMessage::sole();
        $this->assertSame($device->id, $message->device_id);
        $this->assertStringNotContainsString('Private message', $message->body);
        $this->assertSame((string) $buyer->id, $message->data['user_id']);
        $thread->messages()->create(['sender_id' => $buyer->id, 'client_id' => (string) \Illuminate\Support\Str::uuid(), 'body' => 'My reply']);
        $this->assertDatabaseCount('mobile_push_messages', 1);
    }

    public function test_logistics_chat_is_scoped_to_the_riders_current_partner_and_retries_once(): void
    {
        $rider = $this->approvedRider();
        $other = $this->approvedRider();
        $thread = app(\App\Services\LogisticsMessenger::class)->threadFor($rider->rider->provider, $rider);
        app(\App\Services\LogisticsMessenger::class)->send($thread, $rider->rider->provider->owner, 'Your pickup is ready');
        $this->signInMobile($rider)->getJson('/api/mobile/rider/logistics-chat')->assertOk()->assertJsonPath('messages.0.body', 'Your pickup is ready');
        $data = ['body' => 'On my way', 'client_id' => (string) \Illuminate\Support\Str::uuid()];
        $this->postJson('/api/mobile/rider/logistics-chat', $data)->assertCreated();
        $this->postJson('/api/mobile/rider/logistics-chat', $data)->assertCreated();
        $this->assertSame(2, $thread->messages()->count());
        $message = $thread->messages()->first();
        $this->signInMobile($other)->getJson('/api/mobile/rider/logistics-chat/'.$message->id.'/photo')->assertNotFound();
        $this->getJson('/api/mobile/rider/logistics-chat')->assertOk()->assertJsonCount(0, 'messages');
    }

    public function test_fcm_uses_signed_oauth_and_the_project_fid_without_exposing_credentials(): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if (PHP_OS_FAMILY === 'Windows' && is_file('C:/xampp/apache/conf/openssl.cnf')) {
            $options['config'] = 'C:/xampp/apache/conf/openssl.cnf';
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $private, null, $options);
        $file = tempnam(sys_get_temp_dir(), 'zaylo-firebase-fixture-');
        file_put_contents($file, json_encode(['project_id' => 'fixture-project', 'client_email' => 'test@fixture-project.iam.gserviceaccount.com', 'private_key_id' => 'fixture-key', 'private_key' => $private]));
        config(['push.project_id' => 'fixture-project', 'push.credentials' => $file]);
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fixture-access-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/fixture-project/messages/one']),
        ]);
        try {
            $messaging = app(FirebaseMessaging::class);
            $this->assertSame('sent', $messaging->send('fixture-installation-1234', 'Title', 'Body', ['user_id' => '1'], 'event-one'));
            Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
                && $request['message']['fid'] === 'fixture-installation-1234'
                && $request->hasHeader('Authorization', 'Bearer fixture-access-token')
                && ! str_contains($request->body(), 'PRIVATE KEY'));
            Http::assertSent(function ($request) use ($key) {
                if (! str_contains($request->url(), 'oauth2.googleapis.com')) return false;
                [$header, $body, $signature] = explode('.', $request['assertion']);
                return openssl_verify($header.'.'.$body, base64_decode(strtr($signature, '-_', '+/')), openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256) === 1;
            });
        } finally {
            unlink($file);
        }
    }
}
