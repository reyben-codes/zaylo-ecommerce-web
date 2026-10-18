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

    public function test_a_product_appears_under_each_of_its_categories(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Digital Rice Cooker',
            'category' => 'electronics',
            'additional_categories' => ['kitchen_dining', 'electronics'],
            'price' => 1899,
            'stock' => 5,
            'low_stock_threshold' => 2,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $cooker = Product::where('name', 'Digital Rice Cooker')->firstOrFail();
        $shirt = $this->product(overrides: ['name' => 'Everyday Shirt']);

        // The main category is not stored again as an extra one.
        $this->assertSame(['electronics', 'kitchen_dining'], $cooker->category_keys);
        auth()->logout();
        foreach (['electronics', 'kitchen_dining'] as $category) {
            $this->get(route('products.index', ['category' => $category]))
                ->assertOk()->assertSee('Digital Rice Cooker')->assertDontSee('Everyday Shirt');
        }
        $this->get(route('products.index', ['category' => 'home_living']))->assertDontSee('Digital Rice Cooker');
        $this->get(route('products.index', ['search' => 'kitchen']))->assertSee('Digital Rice Cooker');
        // Every category shows as a tag linking to it: on the product page, shop cards and the home page.
        $tags = ['<a class="category-tag" href="'.route('products.index', ['category' => 'electronics']).'">Electronics</a>',
            '<a class="category-tag" href="'.route('products.index', ['category' => 'kitchen_dining']).'">Kitchen &amp; Dining</a>'];
        $this->get(route('products.show', $cooker))->assertSeeInOrder($tags, false);
        $this->get(route('products.index', ['category' => 'kitchen_dining']))->assertSeeInOrder($tags, false);
        $this->get(route('home'))->assertSeeInOrder($tags, false);
        $this->actingAs($seller)->get(route('seller.products'))
            ->assertSee('<a class="category-tag" href="'.route('seller.products', ['category' => 'kitchen_dining']).'">Kitchen &amp; Dining</a>', false);
        auth()->logout();

        // Unticking every extra category removes them.
        $this->actingAs($seller)->put(route('seller.products.update', $cooker), [
            'name' => 'Digital Rice Cooker', 'category' => 'electronics', 'low_stock_threshold' => 2, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['electronics'], $cooker->fresh()->category_keys);
        $this->assertNotNull($shirt);
    }

    public function test_sellers_tick_one_or_more_categories_instead_of_a_dropdown(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()
            ->assertSee('name="categories[]"', false)
            ->assertDontSee('name="category"', false)
            ->assertDontSee('name="additional_categories[]"', false);

        $payload = ['name' => 'Steel Kettle', 'price' => 999, 'stock' => 4, 'is_active' => 1];
        $this->post(route('seller.products.store'), $payload)->assertSessionHasErrors(['categories' => 'Choose at least one category.']);
        $this->post(route('seller.products.store'), $payload + ['categories' => []])->assertSessionHasErrors('categories');
        $this->post(route('seller.products.store'), $payload + ['categories' => ['weapons']])->assertSessionHasErrors('categories.0');
        $this->assertSame(0, Product::count());

        // With no registered shop category, the first ticked one is the main category.
        $this->post(route('seller.products.store'), $payload + ['categories' => ['kitchen_dining', 'home_living']])->assertSessionHasNoErrors();
        $kettle = Product::where('name', 'Steel Kettle')->firstOrFail();
        $this->assertSame(['kitchen_dining', 'home_living'], $kettle->category_keys);
        $this->get(route('seller.products.edit', $kettle))->assertOk()
            ->assertSee('name="categories[]" value="home_living" checked', false)
            ->assertDontSee('name="categories[]" value="electronics" checked', false);

        // Editing keeps the main category while it stays ticked, and moves it when it is unticked.
        $this->put(route('seller.products.update', $kettle), $payload + ['categories' => ['electronics', 'home_living', 'kitchen_dining']])->assertSessionHasNoErrors();
        $this->assertSame(['kitchen_dining', 'electronics', 'home_living'], $kettle->fresh()->category_keys);
        $this->put(route('seller.products.update', $kettle), $payload + ['categories' => ['electronics', 'home_living']])->assertSessionHasNoErrors();
        $this->assertSame(['electronics', 'home_living'], $kettle->fresh()->category_keys);
    }

    public function test_the_main_category_follows_the_sellers_registered_shop_category(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        \App\Models\SellerApplication::create(['user_id' => $seller->id, 'shop_name' => 'Home Shop', 'shop_category' => 'home_living', 'seller_type' => 'individual',
            'tin' => '123-456-789-000', 'region_code' => '130000000', 'region_name' => 'NCR', 'city_code' => '137404000', 'city_name' => 'Quezon City',
            'barangay_code' => '137404001', 'barangay_name' => 'Alicia', 'street_address' => '2F', 'postal_code' => '1121']);

        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Rice Cooker', 'price' => 1500, 'stock' => 3, 'is_active' => 1, 'categories' => ['electronics', 'home_living'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['home_living', 'electronics'], Product::where('name', 'Rice Cooker')->firstOrFail()->category_keys);
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
        // Pending sellers and logistics partners are kept on their application page; suspended partners are signed out.
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'pending']);
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertRedirect(route('seller.application'));

        $partner = User::factory()->create(['role' => 'logistics', 'status' => 'pending']);
        $this->actingAs($partner)->get(route('logistics.dashboard'))->assertRedirect(route('logistics.application'));

        $suspended = User::factory()->create(['role' => 'logistics', 'status' => 'suspended']);
        $this->actingAs($suspended)->get(route('logistics.dashboard'))->assertRedirect(route('logistics.login'));
        $this->assertGuest();
    }

    public function test_seller_registration_requires_verification_and_admin_approval(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'first_name' => 'New', 'last_name' => 'Seller', 'email' => 'new-seller@example.com',
            'password' => 'Strong-password1!', 'password_confirmation' => 'Strong-password1!',
            'role' => 'seller', 'phone' => '09170000000', 'terms' => '1',
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
            'payment_method' => 'cod',
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
