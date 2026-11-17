<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\MobilePushDevice;
use App\Models\MobilePushMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnnouncementPushTest extends TestCase
{
    use RefreshDatabase;

    private function phone(User $user, bool $expired = false): MobilePushDevice
    {
        $bearer = $user->createToken('Announcement phone');
        $sessionId = (int) explode('|', $bearer)[0];
        if ($expired) {
            $user->tokens()->whereKey($sessionId)->update(['expires_at' => now()->subMinute()]);
        }

        return MobilePushDevice::create([
            'user_id' => $user->id, 'mobile_token_id' => $sessionId,
            'token' => 'announcement-phone-'.$sessionId, 'token_hash' => hash('sha256', 'announcement-phone-'.$sessionId),
            'platform' => 'android',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('audiences')]
    public function test_push_respects_audience_and_active_verified_phone_sessions(string $audience): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $expected = [];
        foreach (['buyer', 'seller', 'logistics', 'rider', 'courier'] as $role) {
            $device = $this->phone(User::factory()->create(['role' => $role, 'status' => 'active']));
            if ($audience === 'all' || $role === $audience || ($audience === 'rider' && $role === 'courier')) {
                $expected[] = $device->id;
            }
        }
        foreach ([['status' => 'suspended'], ['email_verified_at' => null], ['archived_at' => now()], ['is_suspended' => true]] as $attributes) {
            $user = User::factory()->create(array_merge(['role' => 'buyer', 'status' => 'active'], $attributes));
            $this->phone($user);
        }
        $this->phone(User::factory()->create(['role' => 'buyer', 'status' => 'active']), expired: true);
        $this->phone($admin);
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Delivery advisory', 'body' => 'Deliveries resume tomorrow.', 'audience' => $audience,
            'send_push' => 1, 'is_published' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Announcement saved. Mobile notifications queued for eligible phones.');
        $announcement = Announcement::sole();
        $this->assertNotNull($announcement->push_queued_at);
        $this->assertEqualsCanonicalizing($expected, MobilePushMessage::pluck('device_id')->all());
        $message = MobilePushMessage::firstOrFail();
        $this->assertSame('Delivery advisory', $message->title);
        $this->assertSame('Deliveries resume tomorrow.', $message->body);
        $this->assertSame('announcement', $message->data['kind']);
        $this->assertSame((string) $announcement->id, $message->data['entity_id']);
    }

    public static function audiences(): array
    {
        return ['everyone' => ['all'], 'buyers' => ['buyer'], 'riders and couriers' => ['rider']];
    }

    public function test_drafts_wait_for_publication_and_updates_or_republishing_do_not_repeat_push(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->phone(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $data = ['title' => 'Service update', 'body' => 'Services resume tomorrow.', 'audience' => 'buyer', 'send_push' => 1];
        $this->actingAs($admin)->post(route('admin.announcements.store'), $data)->assertSessionHasNoErrors();
        $announcement = Announcement::sole();
        $this->assertNull($announcement->push_queued_at);
        $this->assertDatabaseCount('mobile_push_messages', 0);
        $this->patch(route('admin.announcements.update', $announcement), ['toggle' => 1])->assertSessionHasNoErrors();
        $queuedAt = $announcement->fresh()->push_queued_at;
        $this->assertDatabaseCount('mobile_push_messages', 1);
        $this->patch(route('admin.announcements.update', $announcement), $data + ['is_published' => 1])->assertSessionHasNoErrors();
        $this->patch(route('admin.announcements.update', $announcement), ['toggle' => 1]);
        $this->patch(route('admin.announcements.update', $announcement), ['toggle' => 1]);
        $this->assertDatabaseCount('mobile_push_messages', 1);
        $this->assertEquals($queuedAt, $announcement->fresh()->push_queued_at);
    }

    public function test_push_is_optional_and_does_not_silently_schedule_future_delivery(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->phone(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $data = ['title' => 'Service update', 'body' => 'Services resume tomorrow.', 'audience' => 'buyer', 'is_published' => 1];
        $this->actingAs($admin)->post(route('admin.announcements.store'), $data + ['show_in_section' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('mobile_push_messages', 0);
        $this->post(route('admin.announcements.store'), $data + ['send_push' => 1, 'starts_at' => now()->addDays(2)->toDateTimeString()])
            ->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('announcements', 1);
        $this->assertDatabaseCount('mobile_push_messages', 0);
        $this->get(route('admin.settings'))->assertOk()->assertSee('name="send_push"', false)->assertSee('Mobile notification');
    }
}
