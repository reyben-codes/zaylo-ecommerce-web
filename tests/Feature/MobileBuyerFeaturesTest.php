<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\IdentityVerification;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Website buyer features brought to the mobile API: catalog filters, reviews,
 * ID verification, complaints, seller chat, support chat, policies and
 * announcements. Orders use the deployed (legacy) order tables, like the app.
 */
class MobileBuyerFeaturesTest extends TestCase
{
    use SignsInMobile;
    use LegacyOrderTables;
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
        Notification::fake();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Maria Santos']);
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
    }

    private function product(string $name = 'Garden Hose', float $price = 500, array $attributes = []): Product
    {
        return Product::create([
            'seller_id' => $this->seller->id, 'name' => $name, 'category' => 'home_living',
            'price' => $price, 'stock' => 5, 'is_active' => true,
        ] + $attributes);
    }

    /** A legacy order with one item; returns [orderId, orderItemId]. */
    private function order(Product $product, string $status = 'completed', ?User $courier = null): array
    {
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-'.Str::upper(Str::random(6)), 'user_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'status' => $status, 'subtotal' => 500, 'shipping_fee' => 120, 'total' => 620, 'payment_method' => 'cod',
            'recipient_name' => 'Maria Santos', 'phone' => '09170000000', 'shipping_address' => '12 Rizal St, Calamba',
            'placed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_id' => $product->id, 'seller_id' => $this->seller->id,
            'product_name' => $product->name, 'unit_price' => 500, 'quantity' => 1, 'line_total' => 500,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shipments')->insert([
            'order_id' => $orderId, 'courier_id' => $courier?->id, 'tracking_number' => 'TRK-'.$orderId,
            'status' => $status === 'completed' ? 'delivered' : 'assigned', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$orderId, $itemId];
    }

    private function photo(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII='));
    }

    public function test_catalog_supports_the_websites_price_sale_and_gender_filters(): void
    {
        $this->product('Cheap Trowel', 150, ['gender' => 'unisex']);
        $this->product('Mens Boots', 900, ['gender' => 'men']);
        $this->product('Garden Gloves', 400, [
            'sale_type' => 'percent', 'sale_value' => 50, 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay(),
        ]);

        $names = fn ($response) => collect($response->json('products.data'))->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['Cheap Trowel', 'Garden Gloves'], $names($this->getJson('/api/mobile/products?max_price=200')->assertOk()));
        $this->assertSame(['Garden Gloves'], $names($this->getJson('/api/mobile/products?on_sale=1')));
        // Unisex products also appear under Men, as on the website.
        $this->assertEqualsCanonicalizing(['Cheap Trowel', 'Mens Boots'], $names($this->getJson('/api/mobile/products?gender=men')));
        $this->assertSame(['Cheap Trowel', 'Garden Gloves', 'Mens Boots'], $names($this->getJson('/api/mobile/products?sort=price_low')));
        $this->getJson('/api/mobile/products?min_price=500&max_price=100')->assertUnprocessable()->assertJsonValidationErrors('max_price');
        $this->getJson('/api/mobile/products')->assertJsonPath('products.data.0.review_count', 0);
    }

    public function test_buyer_reviews_a_delivered_item_and_the_rating_shows_on_the_product(): void
    {
        Storage::fake('public');
        $product = $this->product();
        [$orderId, $itemId] = $this->order($product);
        [$pendingOrderId, $pendingItemId] = $this->order($product, 'in_transit');
        $this->signInMobile($this->buyer);

        $this->getJson("/api/mobile/orders/$orderId")->assertOk()
            ->assertJsonPath('order.items.0.can_review', true)
            ->assertJsonPath('order.items.0.review_rating', null);
        $this->getJson("/api/mobile/orders/$pendingOrderId")->assertJsonPath('order.items.0.can_review', false);
        $this->postJson("/api/mobile/order-items/$pendingItemId/review", ['rating' => 5])->assertForbidden();

        $this->post("/api/mobile/order-items/$itemId/review", ['rating' => 4, 'comment' => 'Sturdy hose.', 'photos' => [$this->photo()]], ['Accept' => 'application/json'])
            ->assertCreated();
        Storage::disk('public')->assertExists(ProductReview::firstOrFail()->photos[0]);
        $this->postJson("/api/mobile/order-items/$itemId/review", ['rating' => 1])->assertUnprocessable()->assertJsonValidationErrors('review');

        $this->getJson("/api/mobile/orders/$orderId")
            ->assertJsonPath('order.items.0.can_review', false)
            ->assertJsonPath('order.items.0.review_rating', 4);
        $this->getJson("/api/mobile/products/{$product->slug}")
            ->assertJsonPath('product.review_count', 1)
            ->assertJsonPath('product.rating', 4);
        $this->getJson("/api/mobile/products/{$product->slug}/reviews")->assertOk()
            ->assertJsonPath('average', 4)
            ->assertJsonPath('breakdown.4', 1)
            ->assertJsonPath('reviews.0.buyer_name', 'Maria')
            ->assertJsonPath('reviews.0.comment', 'Sturdy hose.')
            ->assertJsonCount(1, 'reviews.0.photos');

        $this->signInMobile(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $this->postJson("/api/mobile/order-items/$itemId/review", ['rating' => 5])->assertForbidden();
    }

    public function test_unverified_buyer_uploads_an_id_and_sees_its_review_status(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->identityUnverified()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($buyer);

        $this->getJson('/api/mobile/me')->assertJsonPath('user.identity_verified', false);
        $this->getJson('/api/mobile/account/identity')->assertOk()
            ->assertJsonPath('identity.verified', false)
            ->assertJsonPath('identity.submission', null)
            ->assertJsonPath('identity.id_types.0.id', 'philid');

        $this->post('/api/mobile/account/identity', ['id_type' => 'passport', 'document' => UploadedFile::fake()->create('id.gif', 10, 'image/gif')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->post('/api/mobile/account/identity', ['id_type' => 'passport', 'document' => $this->photo('id.png')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('identity.submission.status', 'pending')
            ->assertJsonPath('identity.submission.id_type_label', 'Passport');
        Storage::disk('local')->assertExists(IdentityVerification::firstOrFail()->document_path);

        $this->signInMobile($this->buyer);
        $this->post('/api/mobile/account/identity', ['id_type' => 'passport', 'document' => $this->photo('id.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('document');
    }

    public function test_buyer_files_a_complaint_and_the_rider_on_the_delivery_follows_it(): void
    {
        Storage::fake('local');
        $rider = $this->approvedRider(['name' => 'Jun Reyes']);
        [$orderId] = $this->order($this->product(), 'completed', $rider);
        [$cancelledId] = $this->order($this->product('Rake'), 'cancelled');
        $this->signInMobile($this->buyer);

        $this->getJson("/api/mobile/orders/$orderId")->assertJsonPath('order.can_complain', true)->assertJsonPath('order.complaint', null);
        $this->getJson('/api/mobile/complaints/categories')->assertOk()->assertJsonPath('categories.0.id', 'not_received');
        $this->postJson("/api/mobile/orders/$cancelledId/complaint", ['category' => 'damaged', 'details' => str_repeat('x', 30)])->assertStatus(422);
        $this->postJson("/api/mobile/orders/$orderId/complaint", ['category' => 'damaged', 'details' => 'Too short'])
            ->assertUnprocessable()->assertJsonValidationErrors('details');

        $filed = $this->post("/api/mobile/orders/$orderId/complaint", [
            'category' => 'damaged', 'details' => 'The hose arrived with a split near the nozzle.', 'evidence' => [$this->photo('split.png')],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('complaint.status', 'open')
            ->assertJsonPath('complaint.category_label', 'Damaged or defective item')
            ->assertJsonPath('complaint.evidence.0.is_image', true);
        $complaintId = $filed->json('complaint.id');
        $this->get($filed->json('complaint.evidence.0.url'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson("/api/mobile/orders/$orderId/complaint", ['category' => 'other', 'details' => str_repeat('y', 30)])->assertUnprocessable();
        $this->getJson("/api/mobile/orders/$orderId")->assertJsonPath('order.can_complain', false)->assertJsonPath('order.complaint.id', $complaintId);

        $this->signInMobile($rider);
        $this->getJson('/api/mobile/rider/complaints')->assertOk()->assertJsonPath('complaints.0.id', $complaintId);
        $this->postJson("/api/mobile/rider/complaints/$complaintId/messages", ['body' => 'I handed it over in one piece.'])
            ->assertCreated()
            ->assertJsonPath('complaint.status', 'in_review')
            ->assertJsonPath('complaint.messages.0.mine', true);
        $this->getJson("/api/mobile/rider/complaints/$complaintId")->assertJsonPath('complaint.evidence.0.url', route('mobile.rider.complaints.evidence', [$complaintId, 0]));

        $this->signInMobile($this->buyer);
        $this->getJson("/api/mobile/complaints/$complaintId")
            ->assertJsonPath('complaint.messages.0.sender', 'Jun · Rider')
            ->assertJsonPath('complaint.messages.0.mine', false);

        Dispute::findOrFail($complaintId)->update(['status' => 'resolved']);
        $this->postJson("/api/mobile/complaints/$complaintId/messages", ['body' => 'Thanks'])->assertUnprocessable();

        $this->signInMobile(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $this->getJson("/api/mobile/complaints/$complaintId")->assertNotFound();
        $this->get(route('mobile.complaints.evidence', [$complaintId, 0]), ['Accept' => 'application/json'])->assertNotFound();
        $this->getJson('/api/mobile/complaints')->assertJsonCount(0, 'complaints');
    }

    public function test_buyer_chats_with_the_seller_of_a_product(): void
    {
        $product = $this->product();
        $this->signInMobile($this->buyer);

        $started = $this->postJson("/api/mobile/products/{$product->slug}/chat")->assertOk()
            ->assertJsonPath('conversation.product_name', 'Garden Hose');
        $this->postJson("/api/mobile/products/{$product->slug}/chat")->assertOk();
        $this->assertDatabaseCount('conversations', 1);
        $chatId = $started->json('conversation.id');

        $this->postJson("/api/mobile/messages/$chatId", ['body' => 'Is this hose 20 meters?', 'client_id' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('message.mine', true);
        $chat = Conversation::findOrFail($chatId);
        $reply = $chat->messages()->create(['sender_id' => $this->seller->id, 'client_id' => (string) Str::uuid(), 'body' => 'Yes, 20 meters.']);

        $this->getJson('/api/mobile/messages/unread')->assertJsonPath('unread', 1);
        $this->getJson('/api/mobile/messages')->assertOk()
            ->assertJsonPath('conversations.0.unread', 1)
            ->assertJsonPath('conversations.0.preview', 'Yes, 20 meters.');
        $this->getJson("/api/mobile/messages/$chatId")->assertJsonCount(2, 'messages')->assertJsonPath('messages.1.mine', false);
        $this->postJson("/api/mobile/messages/$chatId/read", ['through' => $reply->id])->assertNoContent();
        $this->getJson('/api/mobile/messages/unread')->assertJsonPath('unread', 0);

        $this->signInMobile(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $this->getJson("/api/mobile/messages/$chatId")->assertNotFound();
    }

    public function test_buyer_messages_zaylo_support_in_the_website_thread(): void
    {
        $this->signInMobile($this->buyer);
        $this->postJson('/api/mobile/support/messages', ['body' => 'Where is my refund?', 'client_id' => (string) Str::uuid()])
            ->assertCreated()->assertJsonPath('message.from_staff', false);
        $thread = SupportThread::where('user_id', $this->buyer->id)->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $thread->messages()->create(['sender_id' => $admin->id, 'body' => 'We are checking it now.']);

        $this->getJson('/api/mobile/support/unread')->assertJsonPath('unread', 1);
        $this->getJson('/api/mobile/support')->assertJsonPath('messages.1.sender_name', 'ZAYLO Support');
        $this->getJson('/api/mobile/support/unread')->assertJsonPath('unread', 0);
    }

    public function test_policies_and_announcements_reach_the_app(): void
    {
        $this->getJson('/api/mobile/policies')->assertOk()->assertJsonPath('policies.0.slug', 'terms');
        $terms = $this->getJson('/api/mobile/policies/terms')->assertOk()->json('policy.body');
        $this->assertStringStartsWith('These Terms of Service', $terms);
        $this->assertStringContainsString('- You must be at least 18 years old', $terms);
        $this->getJson('/api/mobile/policies/seller')->assertNotFound();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Announcement::create(['title' => 'Holiday delivery', 'body' => 'Expect delays on Dec 24.', 'audience' => 'buyer', 'created_by' => $admin->id]);
        Announcement::create(['title' => 'Rider meeting', 'body' => 'Friday at 9.', 'audience' => 'rider', 'created_by' => $admin->id]);

        $this->getJson('/api/mobile/home')->assertJsonCount(0, 'announcements');
        $this->signInMobile($this->buyer);
        $this->getJson('/api/mobile/home')->assertJsonCount(1, 'announcements')->assertJsonPath('announcements.0.title', 'Holiday delivery');
    }
}
