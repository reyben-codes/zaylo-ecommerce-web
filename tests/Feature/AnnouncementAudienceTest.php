<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\LogisticsProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->post(route('admin.announcements.store'), ['title' => 'Payout delay', 'body' => 'Payouts move to Monday.', 'audience' => 'seller', 'is_published' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('announcements', ['title' => 'Payout delay', 'audience' => 'seller']);
        $this->post(route('admin.announcements.store'), ['title' => 'Bad', 'body' => 'x', 'audience' => 'admin'])->assertSessionHasErrors('audience');

        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertSee('Payout delay');
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))->get(route('buyer.dashboard'))->assertDontSee('Payout delay');
    }
}
