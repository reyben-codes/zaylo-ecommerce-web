<?php

namespace Tests\Feature;

use App\Models\ComplianceWarning;
use App\Models\IdentityVerification;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Notifications on the Admin Center and Seller Center sidebars, and the seller's warning banner. */
class WorkspaceNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_notified_about_applications_and_listings_and_can_mark_them_read(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        User::factory()->create(['role' => 'seller', 'status' => 'pending', 'name' => 'Pending Boutique']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Product::create(['seller_id' => $seller->id, 'name' => 'Linen shirt', 'category' => 'clothing', 'price' => 500, 'stock' => 5, 'is_active' => true])
            ->submitForReview();
        $this->actingAs($admin);

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.notifications'))
            ->assertSee('seller-sidebar__badge', false);
        $page = $this->get(route('admin.notifications'))->assertOk()
            ->assertSee('New seller application')->assertSee('Pending Boutique is waiting for approval.')
            ->assertSee('Listing to approve')->assertSee('Linen shirt is waiting for review.')
            ->assertViewHas('unreadCount', 2);

        $key = $page->viewData('notifications')->firstWhere('title', 'New seller application')['key'];
        $this->post(route('admin.notifications.open'), ['key' => $key])->assertRedirect(route('admin.registrations'));
        $this->get(route('admin.notifications'))->assertViewHas('unreadCount', 1);

        $this->post(route('admin.notifications.read-all'))->assertRedirect(route('admin.notifications'));
        $this->get(route('admin.notifications'))->assertViewHas('unreadCount', 0);
        $this->get(route('admin.dashboard'))->assertDontSee('seller-sidebar__badge', false);
        $this->post(route('admin.notifications.open'), ['key' => 'nothing:1'])->assertNotFound();
    }

    public function test_sellers_see_stock_listing_and_policy_alerts_with_a_banner_until_read(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $soldOut = Product::create(['seller_id' => $seller->id, 'name' => 'Canvas tote', 'category' => 'bags', 'price' => 300, 'stock' => 0, 'is_active' => true]);
        Product::create(['seller_id' => $seller->id, 'name' => 'Leather belt', 'category' => 'bags', 'price' => 300, 'stock' => 1, 'is_active' => true]);
        $rejected = Product::create(['seller_id' => $seller->id, 'name' => 'Replica watch', 'category' => 'bags', 'price' => 300, 'stock' => 9, 'is_active' => true]);
        $rejected->forceFill(['review_status' => Product::REVIEW_REJECTED, 'review_note' => 'Replicas are not allowed.', 'reviewed_at' => now()])->save();
        ComplianceWarning::create(['seller_id' => $seller->id, 'product_id' => $rejected->id, 'issued_by' => User::factory()->create(['role' => 'admin', 'status' => 'active'])->id, 'reason' => 'prohibited', 'details' => 'Remove replica items.', 'action' => 'warning']);
        $this->actingAs($seller);

        $this->get(route('seller.products'))->assertOk()
            ->assertSee('seller-alert-banner', false)->assertSee('View alerts')
            ->assertSee(route('seller.notifications'));

        $page = $this->get(route('seller.notifications'))->assertOk()
            ->assertSee('Out of stock')->assertSee('Canvas tote has sold out.')
            ->assertSee('Low stock')->assertSee('Leather belt is running low.')
            ->assertSee('Listing not approved')->assertSee('Reason: Replicas are not allowed.')
            ->assertSee('Remove replica items.')
            ->assertDontSee('seller-alert-banner', false);
        $this->assertSame(3, $page->viewData('notifications')->where('urgent', true)->count());

        $key = $page->viewData('notifications')->firstWhere('title', 'Out of stock')['key'];
        $this->post(route('seller.notifications.open'), ['key' => $key])->assertRedirect(route('seller.inventory'));
        $this->assertStringContainsString((string) $soldOut->id, $key);

        $this->post(route('seller.notifications.read-all'));
        $this->get(route('seller.products'))->assertOk()->assertDontSee('seller-alert-banner', false);
    }

    public function test_admin_decisions_show_for_every_admin_and_for_the_account_owner(): void
    {
        $ada = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'Ada Admin']);
        $ben = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'Ben Admin']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'pending', 'name' => 'Linen House']);

        $this->actingAs($ada)->patch(route('admin.users.status', $seller), ['status' => 'active'])->assertSessionHasNoErrors();

        // The admin who decided sees it as history (already read); the other admin sees it as new.
        $this->get(route('admin.notifications'))->assertOk()
            ->assertSeeInOrder(['Account approved', 'You · Linen House is approved and can now sign in.'])
            ->assertViewHas('notifications', fn ($items) => $items->firstWhere('title', 'Account approved')['read'] === true);
        $this->actingAs($ben)->get(route('admin.notifications'))->assertOk()
            ->assertSee('Ada Admin · Linen House is approved and can now sign in.')
            ->assertViewHas('notifications', fn ($items) => $items->firstWhere('title', 'Account approved')['read'] === false);

        // The seller sees the approval; a later suspension and reactivation show too.
        $this->actingAs($seller->fresh())->get(route('seller.notifications'))->assertOk()->assertSee('Your account was approved');
        $this->actingAs($ben)->patch(route('admin.users.status', $seller), ['status' => 'suspended', 'reason' => 'Repeated prohibited listings.'])->assertSessionHasNoErrors();
        $this->patch(route('admin.users.status', $seller), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->get(route('admin.notifications'))->assertSee('Account suspended')->assertSee('Reason: Repeated prohibited listings.')->assertSee('Account reactivated');
        $this->actingAs($seller->fresh())->get(route('seller.notifications'))->assertOk()
            ->assertSeeInOrder(['Your account is active again', 'Your account was suspended', 'Reason: Repeated prohibited listings.', 'Your account was approved']);
    }

    public function test_logistics_partners_see_their_approval_and_rider_applications(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'pending']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Swift Parcels', 'slug' => 'swift-parcels', 'status' => 'pending', 'contact_phone' => '09170000001']);
        $this->actingAs($admin)->patch(route('admin.users.status', $owner), ['status' => 'active'])->assertSessionHasNoErrors();
        $applicant = User::factory()->create(['role' => 'courier', 'status' => 'pending', 'name' => 'Pedro Rider']);
        Rider::create(['user_id' => $applicant->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'pending']);

        $this->actingAs($owner->fresh())->get(route('logistics.dashboard'))->assertOk()
            ->assertSee(route('logistics.notifications'))->assertSee('seller-sidebar__badge', false);
        $this->get(route('logistics.notifications'))->assertOk()
            ->assertSee('Your account was approved')
            ->assertSee('New rider application')->assertSee('Pedro Rider applied to deliver for you.');
        $this->post(route('logistics.notifications.read-all'))->assertRedirect(route('logistics.notifications'));
        $this->get(route('logistics.dashboard'))->assertDontSee('seller-sidebar__badge', false);
    }

    public function test_buyers_are_told_when_their_id_is_approved_or_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $check = IdentityVerification::create(['user_id' => $buyer->id, 'id_type' => 'passport', 'document_path' => 'identity/id.jpg', 'status' => 'pending']);

        $this->actingAs($admin)->patch(route('admin.identity.review', $check), ['decision' => 'reject', 'reason' => 'The photo is too blurry to read.'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->get(route('buyer.notifications'))->assertOk()
            ->assertSee('Your ID was not approved')->assertSee('Reason: The photo is too blurry to read.');
        // The app reads the same feed.
        $token = $this->postJson('/api/mobile/login', ['email' => $buyer->email, 'password' => 'password', 'device_name' => 'Test phone'])->assertOk()->json('token');
        $this->withToken($token)->getJson(route('mobile.notifications'))->assertOk()
            ->assertJsonFragment(['type' => 'account', 'title' => 'Your ID was not approved']);
        $this->actingAs($admin)->get(route('admin.notifications'))->assertSee('ID rejected');
    }

    public function test_notifications_pages_are_role_only(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('admin.notifications'))->assertForbidden();
        $this->actingAs($buyer)->get(route('seller.notifications'))->assertForbidden();
    }
}
