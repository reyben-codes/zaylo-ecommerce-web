<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\LogisticsProvider;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Admin announcements reach only the audience chosen for them; "Everyone" reaches every role and guests.
 */
class AnnouncementAudienceTest extends TestCase
{
    use RefreshDatabase;

    private const NOTICES = [
        'all' => 'Holiday schedule for everyone',
        'buyer' => 'Free shipping weekend for buyers',
        'seller' => 'New fee table for sellers',
        'logistics' => 'Hub audit for logistics partners',
        'rider' => 'Rain gear reminder for riders',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        foreach (self::NOTICES as $audience => $title) {
            Announcement::create(['title' => $title, 'body' => 'Details inside.', 'audience' => $audience, 'is_published' => true, 'created_by' => $admin->id]);
        }
    }

    public function test_each_role_sees_its_own_announcements_and_the_public_one(): void
    {
        $logistics = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        LogisticsProvider::create(['user_id' => $logistics->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        $pages = [
            'buyer' => [User::factory()->create(['role' => 'buyer', 'status' => 'active']), 'buyer.dashboard'],
            'seller' => [User::factory()->create(['role' => 'seller', 'status' => 'active']), 'seller.dashboard'],
            'logistics' => [$logistics, 'logistics.dashboard'],
            'rider' => [User::factory()->create(['role' => 'rider', 'status' => 'active']), 'courier.dashboard'],
        ];

        foreach ($pages as $audience => [$user, $route]) {
            $response = $this->actingAs($user)->get(route($route))->assertOk();
            $response->assertSee(self::NOTICES['all']);
            foreach (self::NOTICES as $other => $title) {
                if ($other === 'all') {
                    continue;
                }
                $other === $audience ? $response->assertSee($title) : $response->assertDontSee($title);
            }
        }
    }

    public function test_guests_see_only_public_announcements_on_the_home_page_and_sign_in(): void
    {
        foreach (['home', 'login'] as $page) {
            $response = $this->get(route($page))->assertOk()->assertSee(self::NOTICES['all']);
            foreach (['buyer', 'seller', 'logistics', 'rider'] as $audience) {
                $response->assertDontSee(self::NOTICES[$audience]);
            }
        }
    }

    public function test_admin_chooses_the_audience_when_posting(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()
            ->assertSee('Who should see it?')
            ->assertSee('name="audience" value="all" checked', false)
            ->assertSee('Everyone (public)')->assertSee('Buyers only')->assertSee('Sellers only')
            ->assertSee('Logistics partners only')->assertSee('Riders only');

        $this->post(route('admin.announcements.store'), ['title' => 'Payout delay', 'body' => 'Payouts move to Monday.', 'audience' => 'seller', 'show_in_section' => 1, 'is_published' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('announcements', ['title' => 'Payout delay', 'audience' => 'seller']);
        $this->post(route('admin.announcements.store'), ['title' => 'Bad', 'body' => 'x', 'audience' => 'admin'])->assertSessionHasErrors('audience');

        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertSee('Payout delay');
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))->get(route('buyer.dashboard'))->assertDontSee('Payout delay');
    }

    public function test_the_chosen_channels_decide_where_an_announcement_appears(): void
    {
        Announcement::query()->delete();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $post = fn (string $title, array $channels) => $this->actingAs($admin)->post(route('admin.announcements.store'), $channels + ['title' => $title, 'body' => 'Details inside.', 'audience' => 'buyer', 'is_published' => 1])->assertSessionHasNoErrors();

        $post('Section only', ['show_in_section' => 1]);
        $post('Popup only', ['show_as_modal' => 1]);
        $post('Both ways', ['show_in_section' => 1, 'show_as_modal' => 1]);
        $this->assertSame(['Both ways', 'Section only'], Announcement::for('buyer')->pluck('title')->sort()->values()->all());
        $this->assertSame(['Both ways', 'Popup only'], Announcement::popupsFor('buyer')->pluck('title')->sort()->values()->all());
        $this->assertEmpty(Announcement::popupsFor('seller'));

        $page = $this->actingAs($buyer)->get(route('buyer.dashboard'))->assertOk();
        $page->assertSee('data-announcement-modal', false)->assertSee('js/announcements.js', false)->assertSee('Popup only');
        $this->actingAs(User::factory()->create(['role' => 'seller', 'status' => 'active']))->get(route('seller.dashboard'))->assertDontSee('data-announcement-modal=', false);
        $this->getJson('/api/mobile/home')->assertJsonMissing(['title' => 'Popup only']);

        Announcement::query()->where('title', 'Popup only')->update(['audience' => 'all']);
        $this->getJson('/api/mobile/home')->assertJsonPath('announcement_popups.0.title', 'Popup only');
        $this->assertNotContains('Popup only', collect($this->getJson('/api/mobile/home')->json('announcements'))->pluck('title'));
    }

    public function test_an_announcement_needs_at_least_one_channel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']))
            ->post(route('admin.announcements.store'), ['title' => 'Nowhere', 'body' => 'Details inside.', 'audience' => 'buyer', 'is_published' => 1])
            ->assertSessionHasErrors('channels');
        $this->assertDatabaseMissing('announcements', ['title' => 'Nowhere']);
    }

    public function test_email_reaches_only_the_active_verified_audience_and_only_once(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active', 'email_verified_at' => now()]);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'email_verified_at' => now()]);
        $unverified = User::factory()->create(['role' => 'rider', 'status' => 'active', 'email_verified_at' => null]);
        $suspended = User::factory()->create(['role' => 'rider', 'status' => 'suspended', 'email_verified_at' => now()]);
        $data = ['title' => 'Rain plan', 'body' => 'Details inside.', 'audience' => 'rider', 'send_email' => 1];

        // A draft sends nothing until it is published, and republishing doesn't send it again.
        $this->actingAs($admin)->post(route('admin.announcements.store'), $data)->assertSessionHasNoErrors();
        Notification::assertNothingSent();
        $announcement = Announcement::where('title', 'Rain plan')->firstOrFail();

        $this->patch(route('admin.announcements.update', $announcement), ['toggle' => 1])->assertSessionHas('status', 'Announcement published. Emailed 1 person.');
        Notification::assertSentTo($rider, AnnouncementPosted::class);
        foreach ([$seller, $unverified, $suspended, $admin] as $other) {
            Notification::assertNotSentTo($other, AnnouncementPosted::class);
        }
        $this->assertNotNull($announcement->fresh()->emailed_at);

        $this->patch(route('admin.announcements.update', $announcement), ['toggle' => 1]);
        $this->patch(route('admin.announcements.update', $announcement), ['toggle' => 1])->assertSessionHas('status', 'Announcement published.');
        Notification::assertSentToTimes($rider, AnnouncementPosted::class, 1);
    }

    public function test_email_cannot_wait_for_a_future_start_time(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']))
            ->post(route('admin.announcements.store'), ['title' => 'Later', 'body' => 'Details inside.', 'audience' => 'buyer', 'send_email' => 1, 'is_published' => 1, 'starts_at' => now()->addDay()->toDateTimeString()])
            ->assertSessionHasErrors('starts_at');
        Notification::assertNothingSent();
    }
}
