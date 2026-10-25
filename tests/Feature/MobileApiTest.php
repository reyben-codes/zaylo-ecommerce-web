<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\PendingRegistration;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\SupportThread;
use App\Models\User;
use App\Notifications\ParcelDeliveryUpdate;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesSignUp;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

/**
 * The mobile app API (/api/mobile): token sign-in, buyer sign-up through the emailed code, the storefront and bag,
 * and the rider app working on parcels its logistics partner assigned.
 */
class MobileApiTest extends TestCase
{
    use CompletesSignUp, PsgcFixtures, RefreshDatabase;

    public function test_a_buyer_signs_up_enters_the_code_and_is_signed_in(): void
    {
        Notification::fake();
        $this->postJson('/api/mobile/register', [
            'first_name' => 'Ana', 'last_name' => 'Reyes', 'phone' => '09171234567', 'date_of_birth' => '2000-01-15',
            'email' => 'ana@example.com', 'password' => 'Strong-password1!', 'password_confirmation' => 'Strong-password1!',
        ])->assertCreated()->assertJson(['email' => 'ana@example.com', 'verification_sent' => true]);

        // As on the website, nothing is an account until the code is entered.
        $this->assertFalse(User::where('email', 'ana@example.com')->exists());
        $this->postJson('/api/mobile/login', ['email' => 'ana@example.com', 'password' => 'Strong-password1!'])
            ->assertForbidden()->assertJson(['code' => 'unverified']);

        $token = $this->postJson('/api/mobile/verify-email', ['email' => 'ana@example.com', 'otp' => $this->signUpCode('ana@example.com'), 'device_name' => 'Pixel 8'])
            ->assertOk()->assertJsonPath('user.role', 'buyer')->json('token');
        $this->assertFalse(PendingRegistration::exists());

        $this->withToken($token)->getJson('/api/mobile/me')->assertOk()->assertJsonPath('user.email', 'ana@example.com');
        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/mobile/me')->assertUnauthorized();
    }

    public function test_sign_in_checks_password_role_and_status_and_answers_in_json(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email' => 'buyer@example.com']);
        User::factory()->create(['role' => 'seller', 'status' => 'active', 'email' => 'seller@example.com']);
        User::factory()->create(['role' => 'buyer', 'status' => 'suspended', 'email' => 'paused@example.com']);

        $this->getJson('/api/mobile/me')->assertUnauthorized();
        $this->get('/api/mobile/me')->assertUnauthorized();
        $this->withToken('1|not-a-real-token')->getJson('/api/mobile/me')->assertUnauthorized();

        $this->postJson('/api/mobile/login', ['email' => 'buyer@example.com', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/mobile/login', ['email' => 'seller@example.com', 'password' => 'password'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', fn ($message) => str_contains($message, 'buyer accounts'));
        $this->postJson('/api/mobile/login', ['email' => 'paused@example.com', 'password' => 'password'])->assertUnprocessable();

        $token = $this->postJson('/api/mobile/login', ['email' => 'BUYER@example.com', 'password' => 'password', 'device_name' => 'iPhone'])
            ->assertOk()->json('token');
        $this->assertMatchesRegularExpression('/^\d+\|\w{40}$/', $token);
        $this->assertSame(1, $buyer->tokens()->count());
        $this->assertNotSame(explode('|', $token)[1], $buyer->tokens()->value('token'));

        $this->assertSame(['auth.failed', 'mobile.login'], ActivityLog::where('user_id', $buyer->id)->orderBy('id')->pluck('event')->all());
    }

    public function test_the_storefront_bag_and_checkout_rules(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'category' => 'home_living', 'price' => 450, 'stock' => 4, 'is_active' => true]);
        Product::create(['seller_id' => $seller->id, 'name' => 'Hidden Lamp', 'category' => 'home_living', 'price' => 99, 'stock' => 4, 'is_active' => false]);

        $this->getJson('/api/mobile/home')->assertOk()->assertJsonPath('new_arrivals.0.slug', $product->slug)->assertJsonMissing(['name' => 'Hidden Lamp']);
        $this->getJson('/api/mobile/products?search=Rattan')->assertOk()->assertJsonPath('products.data.0.name', 'Rattan Basket');
        $this->getJson('/api/mobile/products/'.$product->slug)->assertOk()->assertJsonPath('product.price', 450);

        $buyer = User::factory()->identityUnverified()->create(['role' => 'buyer', 'status' => 'active']);
        $token = $buyer->createToken('test');
        $this->withToken($token)->postJson('/api/mobile/cart/'.$product->slug, ['quantity' => 5])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $item = $this->withToken($token)->postJson('/api/mobile/cart/'.$product->slug, ['quantity' => 2])->assertCreated()->assertJson(['count' => 2])->json('added_item_id');
        $this->withToken($token)->postJson('/api/mobile/wishlist/'.$product->slug)->assertOk()->assertJson(['saved' => true]);
        $this->withToken($token)->getJson('/api/mobile/home')->assertOk()->assertJsonPath('new_arrivals.0.saved', true);

        // Ordering needs an approved ID, exactly as on the website.
        $this->withToken($token)->postJson('/api/mobile/checkout/quote', ['cart_item_ids' => [$item]])->assertOk()
            ->assertJson(['identity_verified' => false, 'totals' => ['subtotal' => 900, 'shipping' => 120]]);
        $address = $buyer->addresses()->create($this->savedAddressData());
        $this->withToken($token)->postJson('/api/mobile/checkout', [
            'address_id' => $address->id, 'idempotency_key' => (string) str()->uuid(), 'cart_item_ids' => [$item],
        ])->assertUnprocessable()->assertJsonValidationErrors('identity');

        // Riders cannot use the buyer endpoints, and nobody can touch someone else's bag.
        [, $provider] = $this->partner('Express');
        $riderToken = $this->rider($provider, 'Marco')->user->createToken('test');
        $this->app['auth']->forgetGuards();
        $this->withToken($riderToken)->getJson('/api/mobile/cart')->assertForbidden()->assertJson(['code' => 'not_buyer']);
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->app['auth']->forgetGuards();
        $this->withToken($other->createToken('test'))->deleteJson('/api/mobile/cart/items/'.$item)->assertForbidden();
    }

    public function test_buyers_manage_saved_addresses(): void
    {
        $this->fakeLocations();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $token = $buyer->createToken('test');

        $this->withToken($token)->postJson('/api/mobile/addresses', $this->addressInput())->assertCreated()
            ->assertJsonPath('address.city', 'City of Bacoor')->assertJsonPath('address.is_default', true);
        $this->withToken($token)->getJson('/api/mobile/addresses')->assertOk()->assertJsonCount(1, 'addresses');
        $this->assertTrue(ActivityLog::where('event', 'mobile.addresses.store')->where('user_id', $buyer->id)->exists());
    }

    public function test_a_rider_works_through_parcels_the_partner_assigned(): void
    {
        [, $provider] = $this->partner('Express Delivery');
        $marco = $this->rider($provider, 'Marco Rider');
        $other = $this->rider($provider, 'Other Rider');
        $parcel = $this->parcel('ZX-0101', $provider, $marco);
        $waiting = $this->parcel('ZX-0102', $provider, null);
        $token = $marco->user->createToken('test');

        $dashboard = $this->withToken($token)->getJson('/api/mobile/rider/dashboard')->assertOk()
            ->assertJsonPath('rider.company', 'Express Delivery')
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('active.0.tracking_number', 'ZX-0101')
            ->assertJsonPath('active.0.next_status', 'out_for_delivery')
            ->assertJsonPath('active.0.next_needs_scan', true)
            ->assertJsonPath('active.0.scan_code', 'ZX-0101')
            ->assertJsonPath('active.0.pickup.store_name', 'Express Delivery')
            ->assertJsonPath('available', []);
        $this->assertNotContains('ZX-0102', collect($dashboard->json('active'))->pluck('tracking_number'));

        $this->withToken($token)->postJson("/api/mobile/rider/deliveries/{$waiting->id}/claim")->assertStatus(409);
        // Starting and confirming a delivery needs the parcel label's QR code, not a button.
        $this->withToken($token)->patchJson("/api/mobile/rider/deliveries/{$parcel->id}", ['status' => 'out_for_delivery'])->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Scan the QR code']);
        $this->withToken($token)->postJson('/api/mobile/rider/deliveries/scan', ['code' => 'ZX-0102'])->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'not one of your deliveries']);
        $this->withToken($token)->postJson('/api/mobile/rider/deliveries/scan', ['code' => ' ZX-0101 '])->assertOk()
            ->assertJsonPath('step', 'out_for_delivery')
            ->assertJsonPath('delivery.status', 'out_for_delivery')->assertJsonPath('delivery.next_status', 'delivered')
            ->assertJsonPath('delivery.next_needs_scan', true)
            ->assertJsonPath('delivery.other_actions.0.status', 'failed')->assertJsonPath('delivery.other_actions.0.needs_scan', false);
        $this->withToken($token)->postJson('/api/mobile/rider/deliveries/scan', ['code' => 'ZX-0101'])->assertOk()
            ->assertJsonPath('step', 'delivered')->assertJsonPath('message', 'Delivered. The buyer and seller were notified.');
        $this->withToken($token)->postJson('/api/mobile/rider/deliveries/scan', ['code' => 'ZX-0101'])->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'already delivered']);

        $this->assertSame('delivered', ShipmentWorkflow::stageOf($parcel->fresh()));
        $this->assertSame('delivered', $parcel->sellerOrder->fresh()->status);
        $this->withToken($token)->getJson('/api/mobile/rider/history')->assertOk()->assertJsonPath('summary.deliveries', 1)->assertJsonPath('deliveries.0.tracking_number', 'ZX-0101');
        $this->withToken($token)->getJson('/api/mobile/rider/earnings')->assertOk()->assertJsonPath('periods.all.deliveries', 1);
        $log = ActivityLog::where('event', 'mobile.rider.deliveries.scan')->latest('id')->first();
        $this->assertSame('Scanned a parcel in the app', $log->description);
        $this->assertSame(['Parcel' => 'ZX-0101'], $log->details());

        // Another rider of the same partner cannot move or scan Marco's parcel.
        $this->app['auth']->forgetGuards();
        $otherToken = $other->user->createToken('test');
        $this->withToken($otherToken)->patchJson("/api/mobile/rider/deliveries/{$parcel->id}", ['status' => 'failed'])->assertForbidden();
        $this->withToken($otherToken)->postJson('/api/mobile/rider/deliveries/scan', ['code' => 'ZX-0101'])->assertUnprocessable();
    }

    public function test_scanning_a_parcel_tells_the_buyer_and_the_seller(): void
    {
        Notification::fake();
        [, $provider] = $this->partner('Express Delivery');
        $marco = $this->rider($provider, 'Marco Rider');
        $parcel = $this->parcel('ZX-0201', $provider, $marco);
        $buyer = $parcel->sellerOrder->order->buyer;
        $seller = User::find(ShipmentWorkflow::sellerUserId($parcel));
        $token = $marco->user->createToken('test');

        $this->withToken($token)->postJson('/api/mobile/rider/deliveries/scan', ['code' => 'ZX-0201'])->assertOk();
        Notification::assertSentTo($buyer, ParcelDeliveryUpdate::class, fn (ParcelDeliveryUpdate $n) => $n->step === 'out_for_delivery');
        Notification::assertNotSentTo($seller, ParcelDeliveryUpdate::class);

        $this->withToken($token)->postJson('/api/mobile/rider/deliveries/scan', ['code' => 'ZX-0201'])->assertOk();
        Notification::assertSentTo($buyer, ParcelDeliveryUpdate::class, fn (ParcelDeliveryUpdate $n) => $n->step === 'delivered');
        Notification::assertSentTo($seller, ParcelDeliveryUpdate::class, fn (ParcelDeliveryUpdate $n) => $n->step === 'delivered');
    }

    public function test_riders_chat_with_support_in_the_app(): void
    {
        [, $provider] = $this->partner('Express Delivery');
        $rider = $this->rider($provider, 'Marco Rider');
        $token = $rider->user->createToken('test');

        $this->withToken($token)->postJson('/api/mobile/rider/support/messages', ['body' => 'My parcel scanner is broken.', 'client_id' => (string) str()->uuid()])
            ->assertCreated()->assertJsonPath('message.from_staff', false);
        $thread = SupportThread::where('user_id', $rider->user_id)->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $thread->messages()->create(['sender_id' => $admin->id, 'body' => 'We will send a new one.']);

        $this->withToken($token)->getJson('/api/mobile/rider/support/unread')->assertOk()->assertJson(['unread' => 1]);
        $this->withToken($token)->getJson('/api/mobile/rider/support')->assertOk()
            ->assertJsonPath('messages.1.from_staff', true)->assertJsonPath('messages.1.sender_name', 'ZAYLO Support');
        $this->withToken($token)->getJson('/api/mobile/rider/support/unread')->assertOk()->assertJson(['unread' => 0]);
    }

    public function test_a_rider_applies_to_a_partner_from_the_app(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->fakeLocations();
        [, $provider] = $this->partner('Express Delivery');
        $application = [
            'first_name' => 'Pedro', 'last_name' => 'Garcia', 'date_of_birth' => '1995-05-05', 'gender' => 'male',
            'region_code' => '040000000', 'province_code' => '042100000', 'city_municipality_code' => '042103000',
            'barangay_code' => '042103001', 'line1' => '5 Rider Street', 'postal_code' => '4102',
            'email' => 'pedro@example.com', 'phone' => '09181234567', 'password' => 'Strong-password1!', 'password_confirmation' => 'Strong-password1!',
            'license_number' => 'n0112345678', 'license_expires_on' => now()->addYear()->toDateString(),
            'vehicle_type' => 'motorcycle', 'plate_number' => '123 abc', 'terms_accepted' => '1',
        ];

        $this->postJson('/api/mobile/logistics-partners')->assertMethodNotAllowed();
        $this->getJson('/api/mobile/logistics-partners')->assertOk()->assertJsonPath('partners.0.name', 'Express Delivery');
        $this->postJson('/api/mobile/rider/register/check', $application)->assertUnprocessable()->assertJsonValidationErrors('logistics_provider_id');
        $application['logistics_provider_id'] = $provider->id;
        $this->postJson('/api/mobile/rider/register/check', $application)->assertOk()->assertJson(['valid' => true]);

        $this->postJson('/api/mobile/rider/register', $application + [
            'license_image' => UploadedFile::fake()->image('license.jpg'),
            'or_cr_image' => UploadedFile::fake()->create('orcr.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJson(['verification_sent' => true]);
        $this->assertFalse(User::where('email', 'pedro@example.com')->exists());

        $this->postJson('/api/mobile/verify-email', ['email' => 'pedro@example.com', 'otp' => $this->signUpCode('pedro@example.com')])
            ->assertOk()->assertJsonMissingPath('token')->assertJsonPath('message', fn ($message) => str_contains($message, 'Express Delivery'));

        $user = User::where('email', 'pedro@example.com')->firstOrFail();
        $rider = $user->rider;
        $this->assertSame(['rider', 'pending', 'male'], [$user->role, $user->status, $user->gender]);
        $this->assertSame([$provider->id, 'pending', 'Motorcycle', '123ABC', 'N01-12-345678'], [$rider->logistics_provider_id, $rider->status, $rider->vehicle_type, $rider->plate_no, $rider->license_number]);
        Storage::disk('local')->assertExists([$rider->license_document_path, $rider->or_cr_document_path]);
        $this->assertSame('City of Bacoor', $user->addresses()->sole()->city);

        // Until the partner approves the application, the rider cannot sign in.
        $this->postJson('/api/mobile/rider/login', ['email' => 'pedro@example.com', 'password' => 'Strong-password1!'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', fn ($message) => str_contains($message, 'Express Delivery'));
    }

    public function test_a_bicycle_rider_applies_without_license_plate_or_documents(): void
    {
        Notification::fake();
        $this->fakeLocations();
        [, $provider] = $this->partner('Express Delivery');
        $application = [
            'first_name' => 'Lito', 'last_name' => 'Ramos', 'date_of_birth' => '1998-03-03', 'gender' => 'male',
            'region_code' => '040000000', 'province_code' => '042100000', 'city_municipality_code' => '042103000',
            'barangay_code' => '042103001', 'line1' => '9 Bike Lane', 'postal_code' => '4102',
            'email' => 'lito@example.com', 'phone' => '09191234567', 'password' => 'Strong-password1!', 'password_confirmation' => 'Strong-password1!',
            'logistics_provider_id' => $provider->id, 'vehicle_type' => 'bicycle', 'terms_accepted' => '1',
        ];

        $this->postJson('/api/mobile/rider/register/check', $application)->assertOk();
        $this->postJson('/api/mobile/rider/register', $application)->assertCreated();
        $this->postJson('/api/mobile/verify-email', ['email' => 'lito@example.com', 'otp' => $this->signUpCode('lito@example.com')])->assertOk();

        $rider = User::where('email', 'lito@example.com')->firstOrFail()->rider;
        $this->assertSame(['Bicycle', null, null, null], [$rider->vehicle_type, $rider->plate_no, $rider->license_number, $rider->license_document_path]);

        // A motorcycle still needs them.
        $this->postJson('/api/mobile/rider/register/check', ['vehicle_type' => 'motorcycle', 'email' => 'other@example.com', 'phone' => '09191234568'] + $application)
            ->assertUnprocessable()->assertJsonValidationErrors(['plate_number', 'license_number', 'license_expires_on']);
    }

    public function test_buyers_continue_with_google_in_the_app(): void
    {
        config(['services.google.client_id' => 'web-client.apps.googleusercontent.com']);
        $claims = fn (array $overrides = []) => $overrides + [
            'aud' => 'web-client.apps.googleusercontent.com', 'iss' => 'https://accounts.google.com',
            'exp' => (string) now()->addHour()->timestamp, 'sub' => '1098765', 'email' => 'gina@example.com',
            'email_verified' => 'true', 'name' => 'Gina Google', 'picture' => 'https://lh3.googleusercontent.com/a/photo',
        ];
        $tokens = [
            'new-buyer' => $claims(),
            'other-app' => $claims(['aud' => 'someone-elses-app.apps.googleusercontent.com']),
            'unverified' => $claims(['sub' => '555', 'email' => 'unverified@example.com', 'email_verified' => 'false']),
            'existing' => $claims(['sub' => '777', 'email' => 'old.buyer@example.com']),
            'seller' => $claims(['sub' => '888', 'email' => 'shop@example.com']),
        ];
        Http::fake(fn (\Illuminate\Http\Client\Request $request) => isset($tokens[$request['id_token']])
            ? Http::response($tokens[$request['id_token']])
            : Http::response(['error' => 'invalid_token'], 400));

        // A first Google sign-in creates a verified buyer, like the website.
        $response = $this->postJson('/api/mobile/google', ['id_token' => 'new-buyer', 'device_name' => 'Pixel'])->assertOk()
            ->assertJsonPath('user.role', 'buyer')->assertJsonPath('user.google_linked', true)->assertJsonPath('user.has_password', false);
        $gina = User::where('email', 'gina@example.com')->sole();
        $this->assertSame(['1098765', 'active', true], [$gina->google_id, $gina->status, $gina->hasVerifiedEmail()]);
        $this->withToken($response->json('token'))->getJson('/api/mobile/me')->assertOk()->assertJsonPath('user.email', 'gina@example.com');
        $this->assertTrue(ActivityLog::where('user_id', $gina->id)->where('event', 'mobile.login')->exists());

        // Signing in again finds the same account.
        $this->postJson('/api/mobile/google', ['id_token' => 'new-buyer'])->assertOk();
        $this->assertSame(1, User::where('email', 'gina@example.com')->count());

        // An existing email-and-password buyer gets Google linked to the same account.
        $old = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email' => 'old.buyer@example.com']);
        $this->postJson('/api/mobile/google', ['id_token' => 'existing'])->assertOk()->assertJsonPath('user.id', $old->id);
        $this->assertSame('777', $old->fresh()->google_id);

        // Tokens for other apps, invalid tokens and unverified emails are refused.
        foreach (['other-app', 'unverified', 'forged'] as $token) {
            $this->postJson('/api/mobile/google', ['id_token' => $token])->assertUnprocessable()->assertJsonValidationErrors('google');
        }
        $this->assertFalse(User::where('email', 'unverified@example.com')->exists());

        // Sellers use the website; the app does not sign them in or link their Google account.
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'email' => 'shop@example.com']);
        $this->postJson('/api/mobile/google', ['id_token' => 'seller'])->assertUnprocessable()
            ->assertJsonPath('errors.google.0', fn ($message) => str_contains($message, 'seller account'));
        $this->assertNull($seller->fresh()->google_id);
        $this->assertSame(0, $seller->tokens()->count());
    }

    public function test_app_buyers_and_website_sellers_message_each_other(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Ana Buyer']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'category' => 'home_living', 'price' => 450, 'stock' => 4, 'is_active' => true]);
        $token = $buyer->createToken('test');
        $asApp = function () use ($token) {
            $this->app['auth']->forgetGuards();

            return $this->withToken($token);
        };
        $asWebsite = function () use ($seller) {
            $this->app['auth']->forgetGuards();

            return $this->withoutToken()->actingAs($seller, 'web');
        };

        // The app opens the same conversation the website's "Chat with seller" would.
        $chatId = $asApp()->postJson('/api/mobile/chat/products/'.$product->slug)->assertOk()
            ->assertJsonPath('conversation.product.name', 'Rattan Basket')->json('conversation.id');
        $this->assertSame($chatId, $asApp()->postJson('/api/mobile/chat/products/'.$product->slug)->json('conversation.id'));
        $this->assertSame(1, Conversation::count());

        // App -> website, with the typing indicator in between.
        $asWebsite()->getJson(route('messages.typing-status'))->assertOk()->assertExactJson(['typing' => []]);
        $asApp()->postJson("/api/mobile/chat/conversations/{$chatId}/typing", ['typing' => true])->assertNoContent();
        $asWebsite()->getJson(route('messages.list', $chatId))->assertOk()->assertJsonPath('typing', true);
        // The website's floating bubble shows the dots before the seller opens the chat.
        $this->getJson(route('messages.typing-status'))->assertJsonCount(1, 'typing')
            ->assertJsonPath('typing.0.conversation_id', $chatId)->assertJsonPath('typing.0.name', 'Ana Buyer');
        $asApp()->getJson('/api/mobile/chat/typing')->assertExactJson(['typing' => []]);
        $asApp()->postJson("/api/mobile/chat/conversations/{$chatId}/messages", ['body' => 'Is this still available?', 'client_id' => (string) str()->uuid()])
            ->assertOk()->assertJsonPath('message.mine', true);
        $asWebsite()->getJson(route('messages.list', $chatId))->assertJsonPath('typing', false)
            ->assertJsonPath('messages.0.body', 'Is this still available?')->assertJsonPath('messages.0.mine', false);
        $this->getJson(route('messages.inbox'))->assertJsonPath('conversations.0.name', 'Ana Buyer')->assertJsonPath('conversations.0.unread', 1);

        // Website -> app: the seller types and replies; the app sees both and marks the reply read.
        $this->postJson(route('messages.typing', $chatId), ['typing' => true])->assertNoContent();
        $asApp()->getJson("/api/mobile/chat/conversations/{$chatId}/messages?after=0")->assertJsonPath('typing', true);
        // ...and the app's floating chat head shows the seller typing too.
        $shopName = $asApp()->getJson('/api/mobile/chat/typing')->assertJsonCount(1, 'typing')
            ->assertJsonPath('typing.0.conversation_id', $chatId)->json('typing.0.name');
        $this->assertNotEmpty($shopName);
        $asWebsite()->postJson(route('messages.send', $chatId), ['body' => 'Yes! Ships tomorrow.', 'client_id' => (string) str()->uuid()])->assertOk();
        $asApp()->getJson('/api/mobile/chat/unread')->assertJson(['unread' => 1]);
        $reply = $asApp()->getJson("/api/mobile/chat/conversations/{$chatId}/messages")->assertOk()
            ->assertJsonPath('typing', false)->assertJsonPath('messages.1.body', 'Yes! Ships tomorrow.')->assertJsonPath('messages.1.mine', false)
            ->json('messages.1.id');
        $asApp()->getJson('/api/mobile/chat/conversations')->assertJsonPath('conversations.0.unread', 1)->assertJsonPath('conversations.0.preview', 'Yes! Ships tomorrow.');
        $asApp()->postJson("/api/mobile/chat/conversations/{$chatId}/read", ['through' => $reply])->assertNoContent();
        $asApp()->getJson('/api/mobile/chat/unread')->assertJson(['unread' => 0]);
        $asApp()->getJson("/api/mobile/chat/conversations/{$chatId}")->assertOk()->assertJsonPath('conversation.id', $chatId);

        // Nobody else can read the conversation.
        $outsider = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->app['auth']->forgetGuards();
        $this->withToken($outsider->createToken('test'))->getJson("/api/mobile/chat/conversations/{$chatId}/messages")->assertNotFound();
    }

    private function partner(string $name): array
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved']);

        return [$owner, $provider];
    }

    private function rider(LogisticsProvider $provider, string $name): Rider
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => $name]);

        return Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);
    }

    /** A parcel sorted at the hub, assigned to $rider (or still waiting for one). */
    private function parcel(string $tracking, LogisticsProvider $provider, ?Rider $rider): Shipment
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active'])->sellers()->create(['name' => 'Shop '.$tracking, 'slug' => 'shop-'.$tracking, 'status' => 'approved']);
        $order = Order::create([
            'buyer_id' => User::factory()->create(['role' => 'buyer'])->id, 'reference' => 'ZAY-'.$tracking, 'total_minor' => 10000,
            'payment_method' => 'cod', 'shipping_address' => ['recipient' => 'Juan Dela Cruz', 'city' => 'Pasig City', 'province' => 'Metro Manila'],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 10000, 'shipping_fee_minor' => 12000, 'commission_minor' => 0, 'status' => 'shipped',
        ]);

        return Shipment::create([
            'seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'tracking_code' => $tracking,
            'sorted_at' => now(), 'sort_area' => 'Pasig City',
        ] + ($rider ? ['rider_id' => $rider->id, 'status' => 'assigned'] : ['status' => ShipmentWorkflow::stored('in_transit')]));
    }
}
