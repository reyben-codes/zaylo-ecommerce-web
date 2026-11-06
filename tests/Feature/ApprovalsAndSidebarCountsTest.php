<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admins approve accounts and products on separate pages, and the Admin, Seller and Logistics Center sidebars count
 * what waits in each section (also returned by each portal's updates endpoint, which the sidebar polls).
 */
class ApprovalsAndSidebarCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_approve_accounts_and_products_on_separate_pages_with_sidebar_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        User::factory()->create(['role' => 'seller', 'status' => 'pending', 'name' => 'Pending Shop Owner']);
        User::factory()->create(['role' => 'logistics', 'status' => 'pending']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $listing = Product::create(['seller_id' => $seller->sellerShop()->id, 'name' => 'Linen Shirt', 'category' => 'storage_bins', 'price' => 500, 'stock' => 5, 'is_active' => true]);
        if (Product::usesReview()) {
            $listing->forceFill(['review_status' => Product::REVIEW_PENDING])->save();
        }
        $this->actingAs($admin);

        $page = $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Account approvals')->assertSee('Product approvals')
            ->assertSee(route('admin.product-approvals'), false)
            ->assertSee('data-sidebar-count="account_approvals" data-sidebar-count-label="waiting" >2', false)
            ->assertSee('data-seller-updates-url="'.route('admin.updates').'"', false)
            ->assertSee('js/seller-live-updates.js', false);

        // Accounts and listings are on their own pages.
        $this->get(route('admin.registrations'))->assertOk()->assertSee('Pending Shop Owner')->assertDontSee('Linen Shirt');
        if (Product::usesReview()) {
            $page->assertSee('data-sidebar-count="product_approvals" data-sidebar-count-label="waiting" >1', false);
            $this->get(route('admin.product-approvals'))->assertOk()->assertSee('Product approvals')
                ->assertSee('Linen Shirt')->assertSee(route('admin.compliance.review', $listing), false)->assertDontSee('Pending Shop Owner');
            // Compliance no longer holds the queue; old links to it open Product approvals.
            $this->get(route('admin.compliance'))->assertOk()->assertSee('Seller compliance')->assertDontSee('Pending review <span>', false);
            $this->get(route('admin.compliance', ['view' => 'pending']))->assertRedirect(route('admin.product-approvals', ['view' => 'pending']));
        }

        $this->getJson(route('admin.updates'))->assertOk()
            ->assertJsonPath('badges.account_approvals', 2)
            ->assertJsonPath('badges.product_approvals', Product::usesReview() ? 1 : 0)
            ->assertJsonPath('badges.identity', 0);
    }

    public function test_seller_and_logistics_sidebars_count_what_waits_in_each_section(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $rejected = Product::create(['seller_id' => $seller->sellerShop()->id, 'name' => 'Replica Watch', 'category' => 'storage_bins', 'price' => 500, 'stock' => 5, 'is_active' => true]);
        if (Product::usesReview()) {
            $rejected->forceFill(['review_status' => Product::REVIEW_REJECTED, 'review_note' => 'Replicas are not allowed.'])->save();
        }
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()
            ->assertSee('data-sidebar-count="chat"', false)
            ->assertSee('data-sidebar-count="logistics_chat"', false);
        $this->getJson(route('seller.updates'))->assertOk()
            ->assertJsonPath('badges.products', Product::usesReview() ? 1 : 0)
            ->assertJsonPath('badges.chat', 0)
            ->assertJsonStructure(['signature', 'badges' => ['orders', 'returns', 'notifications', 'products', 'chat', 'logistics_chat']]);

        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        $applicant = User::factory()->create(['role' => 'rider', 'status' => 'pending']);
        Rider::create([
            'user_id' => $applicant->id, 'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'NBC1234',
            'license_number' => 'S33-45-666666', 'license_expires_on' => now()->addYear()->toDateString(), 'status' => 'pending', 'is_active' => true,
        ]);

        $this->actingAs($owner)->get(route('logistics.dashboard'))->assertOk()
            ->assertSee('data-sidebar-count="riders" data-sidebar-count-label="to review" >1', false)
            ->assertSee('data-sidebar-count="pickups"', false)
            ->assertSee('data-seller-updates-url="'.route('logistics.updates').'"', false);
        $this->getJson(route('logistics.updates'))->assertOk()
            ->assertJsonPath('badges.riders', 1)
            ->assertJsonPath('badges.pickups', 0)
            ->assertJsonStructure(['badges' => ['notifications', 'pickups', 'incoming', 'sorting', 'assignments', 'returns', 'riders', 'messages']]);

        // Each portal reads only its own counts.
        $this->actingAs($seller)->getJson(route('logistics.updates'))->assertForbidden();
    }
}
