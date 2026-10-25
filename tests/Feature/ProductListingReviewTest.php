<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ProductReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Seller listings wait for an admin before buyers can see or buy them. */
class ProductListingReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_a_new_listing_is_hidden_from_buyers_until_an_admin_approves_it(): void
    {
        $this->actingAs($this->seller)->post(route('seller.products.store'), $this->listing())
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($status) => str_contains($status, 'once an admin approves it'));
        $product = Product::where('name', 'Linen Shirt')->firstOrFail();
        $this->assertTrue($product->is_pending_review);
        $this->assertFalse($product->is_listed);

        // The seller can preview it and sees why it is not live.
        $this->get(route('products.show', $product))->assertOk()->assertSee('Preview — buyers cannot see this product.');
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('Waiting for admin approval.');
        $this->get(route('seller.products'))->assertOk()->assertSee('Pending review');

        // Buyers and guests cannot find, open, or buy it, on the web or in the app.
        auth()->logout();
        $this->get(route('products.show', $product))->assertNotFound();
        $this->get(route('products.index', ['search' => 'Linen']))->assertOk()->assertDontSee('Linen Shirt');
        $this->getJson('/api/mobile/products/'.$product->slug)->assertNotFound();
        $this->getJson('/api/mobile/products?search=Linen')->assertOk()->assertJsonMissing(['name' => 'Linen Shirt']);

        // The admin sees it in the queue (opened by default while listings wait) and on the dashboard.
        $this->actingAs($this->admin)->get(route('admin.compliance'))->assertOk()
            ->assertSee('Linen Shirt')->assertSee('Pending review')->assertSee(route('admin.compliance.review', $product), false);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('product listings to approve');
        $this->get(route('products.show', $product))->assertOk()->assertSee('waiting for an admin to approve it');

        $this->patch(route('admin.compliance.review', $product), ['decision' => 'approve'])
            ->assertSessionHas('status', 'Linen Shirt is approved and now visible to buyers.');
        $product->refresh();
        $this->assertTrue($product->is_listed);
        $this->assertSame($this->admin->id, $product->reviewed_by);
        Notification::assertSentTo($this->seller, ProductReviewed::class, fn ($mail) => $mail->product->is($product));
        $this->assertSame(['Decision' => 'Approve'], ActivityLog::where('event', 'admin.compliance.review')->latest('id')->first()->details());

        auth()->logout();
        $this->get(route('products.show', $product))->assertOk()->assertDontSee('Preview — buyers cannot see this product.');
        $this->get(route('products.index', ['search' => 'Linen']))->assertSee('Linen Shirt');
    }

    public function test_a_rejected_listing_tells_the_seller_why_and_goes_back_for_review_once_fixed(): void
    {
        $this->actingAs($this->seller)->post(route('seller.products.store'), $this->listing(['name' => 'Replica Watch']));
        $product = Product::where('name', 'Replica Watch')->firstOrFail();

        $this->actingAs($this->admin)->patch(route('admin.compliance.review', $product), ['decision' => 'reject'])
            ->assertSessionHasErrors('reason');
        $this->patch(route('admin.compliance.review', $product), ['decision' => 'reject', 'reason' => 'Replicas of branded watches are not allowed.'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->is_rejected);
        Notification::assertSentTo($this->seller, ProductReviewed::class, fn ($mail) => $mail->toMail($this->seller)->subject === 'Your product "Replica Watch" was not approved');
        $this->get(route('admin.compliance', ['view' => 'rejected']))->assertSee('Replica Watch')->assertSee('Replicas of branded watches are not allowed.');

        $this->actingAs($this->seller)->get(route('seller.products.edit', $product))
            ->assertSee('Reason: Replicas of branded watches are not allowed.');
        // Saving the fixed listing sends it back to the queue.
        $this->put(route('seller.products.update', $product), $this->listing(['name' => 'Classic Steel Watch']))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'sent for review'));
        $product->refresh();
        $this->assertTrue($product->is_pending_review);
        $this->assertNull($product->review_note);
    }

    public function test_editing_what_buyers_see_needs_approval_again_but_price_and_stock_do_not(): void
    {
        $this->actingAs($this->seller)->post(route('seller.products.store'), $this->listing());
        $product = Product::where('name', 'Linen Shirt')->firstOrFail();
        $product->forceFill(['review_status' => Product::REVIEW_APPROVED])->save();

        $this->put(route('seller.products.update', $product), $this->listing(['price' => 799, 'stock' => 20]))
            ->assertSessionHas('status', 'Product updated successfully.');
        $this->assertTrue($product->fresh()->is_listed);

        $this->put(route('seller.products.update', $product), $this->listing(['description' => 'Now with a hidden pocket for anything.']));
        $this->assertTrue($product->fresh()->is_pending_review);
    }

    public function test_sellers_cannot_approve_their_own_listing(): void
    {
        $this->actingAs($this->seller)->post(route('seller.products.store'), $this->listing() + ['review_status' => 'approved']);
        $product = Product::where('name', 'Linen Shirt')->firstOrFail();
        $this->assertTrue($product->is_pending_review);

        $this->patch(route('admin.compliance.review', $product), ['decision' => 'approve'])->assertForbidden();
        $this->assertTrue($product->fresh()->is_pending_review);
    }

    private function listing(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Linen Shirt',
            'category' => 'fashion',
            'description' => 'A breathable shirt for warm days.',
            'price' => 650,
            'stock' => 10,
            'low_stock_threshold' => 2,
            'is_active' => 1,
        ];
    }
}
