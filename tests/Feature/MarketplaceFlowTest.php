<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use App\Notifications\VerifyEmailCode;
use Tests\TestCase;

class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\PsgcFixtures;

    public function test_guests_can_browse_active_products(): void
    {
        $product = $this->product();

        $this->get(route('products.index'))->assertOk()->assertSee($product->name);
        $this->get(route('products.show', $product))->assertOk()->assertSee($product->description);
    }

    public function test_marketplace_supports_broad_product_categories(): void
    {
        $electronics = $this->product(overrides: [
            'name' => 'Wireless Earbuds',
            'category' => 'electronics',
            'gender' => null,
        ]);
        $fashion = $this->product(overrides: ['name' => 'Everyday Jacket']);

        $this->get(route('products.index', ['category' => 'electronics']))
            ->assertOk()
            ->assertSee($electronics->name)
            ->assertDontSee($fashion->name);

        $this->get(route('products.index', ['category' => 'fashion']))
            ->assertOk()
            ->assertSee($fashion->name)
            ->assertDontSee($electronics->name);
    }

    public function test_sellers_can_list_products_outside_fashion(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Smart Home Speaker',
            'category' => 'electronics',
            'description' => 'A compact speaker for a connected home.',
            'price' => 1499,
            'stock' => 12,
            'low_stock_threshold' => 3,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'seller_id' => $seller->id,
            'name' => 'Smart Home Speaker',
            'category' => 'electronics',
        ]);
    }

    public function test_homepage_displays_new_arrivals_from_the_active_catalog(): void
    {
        $product = $this->product(overrides: ['name' => 'New Arrival Coat']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('New Arrivals')
            ->assertSee($product->name)
            ->assertSee(route('products.show', $product))
            ->assertSee(route('register', ['role' => 'seller']))
            ->assertSee(route('register', ['role' => 'courier']));

        $this->get(route('register', ['role' => 'courier']))
            ->assertOk()
            ->assertSee('type="hidden" name="role" id="selectedRole" value="courier"', false);
    }

    public function test_pending_marketplace_accounts_cannot_open_dashboards(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'pending']);

        $this->actingAs($seller)->get(route('seller.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_seller_registration_requires_verification_and_admin_approval(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'first_name' => 'New', 'last_name' => 'Seller', 'email' => 'new-seller@example.com',
            'password' => 'strong-password', 'password_confirmation' => 'strong-password',
            'role' => 'seller', 'phone' => '09170000000',
        ])->assertRedirect(route('verification.notice'));

        $seller = User::where('email', 'new-seller@example.com')->firstOrFail();
        $this->assertSame('pending', $seller->status);
        $this->assertDatabaseHas('seller_profiles', ['user_id' => $seller->id]);
        Notification::assertSentTo($seller, VerifyEmailCode::class);
    }

    public function test_sellers_cannot_edit_another_sellers_product(): void
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = $this->product($owner);

        $this->actingAs($other)->put(route('seller.products.update', $product), [
            'name' => 'Changed', 'category' => 'clothing', 'gender' => 'unisex',
            'price' => 100, 'stock' => 1, 'low_stock_threshold' => 1,
        ])->assertForbidden();
    }

    public function test_order_moves_from_cart_through_delivery_without_overselling(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $courier = User::factory()->create(['role' => 'courier', 'status' => 'active']);
        $product = $this->product($seller, ['price' => 250, 'stock' => 5]);

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => 2])
            ->assertRedirect(route('buyer.cart'));
        $address = $buyer->addresses()->create($this->savedAddressData());
        $this->post(route('buyer.checkout'), [
            'address_id' => $address->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('buyer.orders'));

        $order = Order::firstOrFail();
        $this->assertSame('620.00', $order->total);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);

        $this->actingAs($seller)->patch(route('seller.orders.status', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->patch(route('seller.orders.status', $order), ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->patch(route('seller.orders.status', $order), ['status' => 'ready_for_pickup'])->assertSessionHasNoErrors();

        $shipment = Shipment::firstOrFail();
        $this->actingAs($courier)->post(route('courier.deliveries.claim', $shipment))->assertSessionHasNoErrors();
        $this->patch(route('courier.deliveries.update', $shipment), ['status' => 'picked_up'])->assertSessionHasNoErrors();
        $this->patch(route('courier.deliveries.update', $shipment), ['status' => 'in_transit'])->assertSessionHasNoErrors();
        $this->patch(route('courier.deliveries.update', $shipment), ['status' => 'delivered'])->assertSessionHasNoErrors();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('paid', $order->payment->fresh()->status);
        $this->assertDatabaseHas('commissions', ['order_id' => $order->id, 'status' => 'earned']);
    }

    public function test_admin_can_approve_a_pending_seller(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'pending']);

        $this->actingAs($admin)->patch(route('admin.users.status', $seller), ['status' => 'active'])
            ->assertRedirect();
        $this->assertSame('active', $seller->fresh()->status);
        $this->assertSame($admin->id, $seller->fresh()->approved_by);
    }

    private function product(?User $seller = null, array $overrides = []): Product
    {
        $seller ??= User::factory()->create(['role' => 'seller', 'status' => 'active']);

        return Product::create(array_merge([
            'seller_id' => $seller->id, 'name' => 'Test Linen Shirt', 'category' => 'clothing',
            'gender' => 'unisex', 'description' => 'A product used to verify the marketplace flow.',
            'price' => 500, 'stock' => 10, 'is_active' => true,
        ], $overrides));
    }
}
