<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Conversation;
use App\Models\DeliveryMessage;
use App\Models\Dispute;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The endpoints the current mobile app (mobile branch) calls besides the basics in MobileApiTest: reviews, policies,
 * ID verification, the Messages inbox, Help & support, rider–buyer delivery chat, complaints, and the rider
 * dashboard's action list. The payload keys asserted here are the ones the Flutter models parse.
 */
class MobileAppFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_sends_announcements_ratings_reviews_and_policies(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'category' => 'home_living', 'price' => 450, 'stock' => 4, 'is_active' => true]);
        $pending = Product::create(['seller_id' => $seller->id, 'name' => 'Unchecked Lamp', 'category' => 'home_living', 'price' => 99, 'stock' => 4, 'is_active' => true]);
        $pending->submitForReview();
        Announcement::create(['title' => 'Holiday sale', 'body' => 'Free shipping all week.', 'audience' => 'all', 'is_published' => true,
            'created_by' => User::factory()->create(['role' => 'admin', 'status' => 'active'])->id]);

        $this->getJson('/api/mobile/home')->assertOk()
            ->assertJsonPath('announcements.0.title', 'Holiday sale')
            ->assertJsonPath('new_arrivals.0.review_count', 0)
            ->assertJsonMissing(['name' => 'Unchecked Lamp']);
        $this->getJson('/api/mobile/products?on_sale=1')->assertOk()->assertJsonPath('products.total', 0);
        $this->getJson('/api/mobile/products?min_price=400&max_price=500&sort=price_low')->assertOk()->assertJsonPath('products.data.0.name', 'Rattan Basket');
        $this->getJson('/api/mobile/products/'.$product->slug)->assertOk()->assertJsonPath('product.review_count', 0);

        $this->getJson('/api/mobile/products/'.$product->slug.'/reviews')->assertOk()
            ->assertJson(['count' => 0, 'average' => null, 'page' => 1])->assertJsonPath('breakdown.5', 0);
        // Listings an admin has not approved stay out of the app, reviews included.
        $this->getJson('/api/mobile/products/'.$pending->slug.'/reviews')->assertNotFound();
        $this->getJson('/api/mobile/products/'.$pending->slug)->assertNotFound();

        $this->getJson('/api/mobile/policies')->assertOk()->assertJsonCount(4, 'policies')->assertJsonPath('policies.0.slug', 'terms');
        $terms = $this->getJson('/api/mobile/policies/terms')->assertOk()->json('policy.body');
        $this->assertStringContainsString('## 1. Who can use ZAYLO', $terms);
        $this->assertStringContainsString('- You must be at least 18 years old', $terms);
        $privacy = $this->getJson('/api/mobile/policies/privacy')->assertOk()->assertJsonPath('policy.title', 'Privacy Policy')->json('policy');
        $this->assertStringContainsString('## 8. Your rights', $privacy['body']);
        $this->assertNotNull($privacy['updated_at']);
        foreach (['returns', 'prohibited'] as $slug) {
            $this->getJson('/api/mobile/policies/'.$slug)->assertOk()->assertJsonPath('policy.slug', $slug);
        }
        $this->getJson('/api/mobile/policies/seller')->assertNotFound();
    }

    public function test_buyers_upload_their_id_in_the_app(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->identityUnverified()->create(['role' => 'buyer', 'status' => 'active']);
        $token = $buyer->createToken('test');

        $this->withToken($token)->getJson('/api/mobile/account/identity')->assertOk()
            ->assertJson(['identity' => ['enabled' => true, 'verified' => false, 'submission' => null]])
            ->assertJsonPath('identity.id_types.0.id', array_key_first(\App\Models\IdentityVerification::ID_TYPES));

        $this->withToken($token)->post('/api/mobile/account/identity', [
            'id_type' => array_key_first(\App\Models\IdentityVerification::ID_TYPES),
            'document' => UploadedFile::fake()->image('id.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('identity.submission.status', 'pending');
        Storage::disk('local')->assertExists($buyer->latestIdentityVerification()->first()->document_path);
    }

    public function test_the_messages_inbox_shares_threads_with_the_website(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'category' => 'home_living', 'price' => 450, 'stock' => 4, 'is_active' => true]);
        $token = $buyer->createToken('test');

        $this->withToken($token)->getJson('/api/mobile/messages')->assertOk()->assertJsonCount(0, 'conversations');
        $chat = $this->withToken($token)->postJson('/api/mobile/products/'.$product->slug.'/chat')->assertOk()
            ->assertJsonPath('conversation.product_name', 'Rattan Basket')->assertJsonPath('conversation.unread', 0)->json('conversation.id');
        $this->withToken($token)->postJson("/api/mobile/messages/{$chat}", ['body' => 'Is it in stock?', 'client_id' => (string) str()->uuid()])
            ->assertOk()->assertJsonPath('message.mine', true);
        $this->withToken($token)->postJson("/api/mobile/messages/{$chat}/typing", ['typing' => true])->assertNoContent();

        // The seller answers from the website.
        $this->app['auth']->forgetGuards();
        $reply = Conversation::find($chat)->messages()->create(['sender_id' => $seller->id, 'body' => 'Yes, 4 left.', 'client_id' => (string) str()->uuid()]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/mobile/messages/unread')->assertOk()->assertJson(['unread' => 1]);
        $this->withToken($token)->getJson('/api/mobile/messages')->assertOk()
            ->assertJsonPath('conversations.0.preview', 'Yes, 4 left.')->assertJsonPath('conversations.0.last_message_mine', false)
            ->assertJsonPath('conversations.0.unread', 1);
        $this->withToken($token)->getJson("/api/mobile/messages/{$chat}")->assertOk()->assertJsonCount(2, 'messages')->assertJsonPath('has_older', false);
        $this->withToken($token)->postJson("/api/mobile/messages/{$chat}/read", ['through' => $reply->id])->assertNoContent();
        $this->withToken($token)->getJson('/api/mobile/messages/unread')->assertJson(['unread' => 0]);
    }

    public function test_buyers_chat_with_support_and_retries_are_stored_once(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $token = $buyer->createToken('test');
        $clientId = (string) str()->uuid();

        foreach ([1, 2] as $attempt) {
            $this->withToken($token)->postJson('/api/mobile/support/messages', ['body' => 'Where is my refund?', 'client_id' => $clientId])
                ->assertCreated()->assertJsonPath('message.client_id', $clientId);
        }
        $this->assertSame(1, SupportMessage::count());

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        SupportMessage::first()->thread->messages()->create(['sender_id' => $admin->id, 'body' => 'We are checking it.']);
        $this->withToken($token)->getJson('/api/mobile/support/unread')->assertOk()->assertJson(['unread' => 1]);
        $this->withToken($token)->getJson('/api/mobile/support')->assertOk()
            ->assertJsonPath('messages.1.from_staff', true)->assertJsonPath('messages.1.client_id', 'server-'.SupportMessage::latest('id')->value('id'));
    }

    public function test_rider_and_buyer_chat_while_the_parcel_is_out_and_the_dashboard_lists_actions(): void
    {
        [$provider, $rider] = $this->riderOfPartner();
        $parcel = $this->parcel('ZX-0301', $provider, $rider);
        $order = $parcel->sellerOrder->order;
        $buyerToken = $order->buyer->createToken('test');
        $riderToken = $rider->user->createToken('test');

        $this->getJson('/api/mobile/rider/logistics-partners')->assertOk()
            ->assertJsonPath('partners.0.name', 'Express Delivery')->assertJsonPath('unlicensed_vehicle_types', ['Bicycle']);
        $this->withToken($riderToken)->getJson('/api/mobile/rider/dashboard')->assertOk()
            ->assertJsonPath('rider.logistics_partner', 'Express Delivery')
            ->assertJsonPath('delivery_mode_message', null)
            ->assertJsonPath('active.0.actions.0.status', 'out_for_delivery')
            ->assertJsonPath('active.0.actions.0.needs_scan', true)
            ->assertJsonPath('active.0.chat_open', true)
            ->assertJsonPath('active.0.unread_messages', 0);

        $clientId = (string) str()->uuid();
        foreach ([1, 2] as $attempt) {
            $this->withToken($riderToken)->postJson("/api/mobile/rider/deliveries/{$parcel->id}/chat", ['body' => 'I am at the gate.', 'client_id' => $clientId])
                ->assertCreated()->assertJsonPath('message.mine', true);
        }
        $this->assertSame(1, DeliveryMessage::count());

        $this->app['auth']->forgetGuards();
        $this->withToken($buyerToken)->getJson("/api/mobile/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.rider_chat.rider_name', 'Marco')->assertJsonPath('order.rider_chat.unread', 1)
            ->assertJsonPath('order.can_complain', true)->assertJsonPath('order.complaint', null);
        $this->withToken($buyerToken)->getJson("/api/mobile/orders/{$order->id}/chat")->assertOk()
            ->assertJsonPath('chat.open', true)->assertJsonPath('chat.other_role', 'Rider')->assertJsonPath('chat.messages.0.mine', false);
        $this->withToken($buyerToken)->postJson("/api/mobile/orders/{$order->id}/chat", ['body' => 'Coming down now.'])->assertCreated();

        // Once delivered, the thread stays readable but takes no new messages.
        $parcel->update(['status' => ShipmentWorkflow::stored('delivered')]);
        $this->withToken($buyerToken)->postJson("/api/mobile/orders/{$order->id}/chat", ['body' => 'Thanks!'])
            ->assertUnprocessable()->assertJsonValidationErrors(['body' => 'delivered']);

        // Other people cannot read the thread.
        $stranger = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->app['auth']->forgetGuards();
        $this->withToken($stranger->createToken('test'))->getJson("/api/mobile/orders/{$order->id}/chat")->assertNotFound();
    }

    public function test_buyers_file_and_follow_complaints_in_the_app(): void
    {
        Notification::fake();
        Storage::fake('local');
        [$provider, $rider] = $this->riderOfPartner();
        $order = $this->parcel('ZX-0401', $provider, $rider)->sellerOrder->order;
        $token = $order->buyer->createToken('test');

        $this->withToken($token)->getJson('/api/mobile/complaints/categories')->assertOk()->assertJsonPath('max_files', Dispute::MAX_EVIDENCE);
        $this->withToken($token)->postJson("/api/mobile/orders/{$order->id}/complaint", ['category' => 'damaged', 'details' => 'Too short'])
            ->assertUnprocessable()->assertJsonValidationErrors('details');
        $case = $this->withToken($token)->post("/api/mobile/orders/{$order->id}/complaint", [
            'category' => 'damaged', 'details' => 'The basket arrived crushed on one side.',
            'evidence' => [UploadedFile::fake()->image('crushed.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('complaint.category_label', 'Damaged or defective item')->assertJsonPath('complaint.can_reply', true)
            ->assertJsonPath('complaint.evidence.0.is_image', true)->json('complaint.id');

        $this->withToken($token)->getJson('/api/mobile/complaints')->assertOk()->assertJsonPath('complaints.0.id', $case);
        $this->withToken($token)->postJson("/api/mobile/complaints/{$case}/messages", ['body' => 'Photos attached.'])
            ->assertCreated()->assertJsonPath('complaint.messages.0.mine', true);
        $this->withToken($token)->postJson("/api/mobile/orders/{$order->id}/complaint", ['category' => 'other', 'details' => 'A second complaint about the same order.'])
            ->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->withToken($rider->user->createToken('test'))->getJson('/api/mobile/rider/complaints')->assertOk();
    }

    public function test_reviews_wait_for_delivery(): void
    {
        [$provider, $rider] = $this->riderOfPartner();
        $order = $this->parcel('ZX-0501', $provider, $rider)->sellerOrder->order;
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'category' => 'home_living', 'price' => 450, 'stock' => 4, 'is_active' => true]);
        $item = $order->sellerOrders()->first()->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $product->defaultVariant?->id, 'product_name' => 'Rattan Basket', 'variant_name' => 'Default', 'unit_price_minor' => 45000, 'quantity' => 1,
        ]);

        $this->withToken($order->buyer->createToken('test'))->postJson("/api/mobile/order-items/{$item->id}/review", ['rating' => 5])
            ->assertForbidden();
    }

    /** @return array{0: LogisticsProvider, 1: Rider} */
    private function riderOfPartner(): array
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => 'Marco Rider']);

        return [$provider, Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true])];
    }

    /** A parcel assigned to $rider, for an order a buyer placed. */
    private function parcel(string $tracking, LogisticsProvider $provider, Rider $rider): Shipment
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active'])->sellers()->create(['name' => 'Shop '.$tracking, 'slug' => 'shop-'.$tracking, 'status' => 'approved']);
        $order = Order::create([
            'buyer_id' => User::factory()->create(['role' => 'buyer', 'status' => 'active'])->id, 'reference' => 'ZAY-'.$tracking, 'total_minor' => 10000,
            'payment_method' => 'cod', 'shipping_address' => ['recipient' => 'Juan Dela Cruz', 'city' => 'Pasig City', 'province' => 'Metro Manila'],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 10000, 'shipping_fee_minor' => 12000, 'commission_minor' => 0, 'status' => 'shipped',
        ]);

        return Shipment::create([
            'seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'tracking_code' => $tracking,
            'rider_id' => $rider->id, 'status' => 'assigned',
        ]);
    }
}
