<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class SellerCenterTest extends TestCase
{
    use DatabaseMigrations, PsgcFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useDeployedOrderSchema();
    }

    public function test_checkout_orders_reach_the_seller_dashboard_orders_and_reports(): void
    {
        [$seller, $product] = $this->sellerWithProduct();
        // Make the shop id differ from the seller's user id, as on the live database.
        $this->assertNotSame($seller->id, $seller->sellerShop()->id);
        $buyer = $this->buyerWhoOrders($product, 2);
        $order = Order::firstOrFail();
        $this->assertSame($seller->sellerShop()->id, (int) $order->seller_id);

        $this->actingAs($seller)->get(route('seller.orders'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($buyer->name)
            ->assertSee('Confirm order');

        foreach (['confirmed', 'processing', 'ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'completed'] as $status) {
            $this->patch(route('seller.orders.status', $order), ['status' => $status])->assertSessionHasNoErrors();
        }
        $this->assertSame('completed', $order->fresh()->status);

        $this->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Happy Heels')
            ->assertSee($order->order_number)
            ->assertSee('2,000.00')
            ->assertDontSee('#ZYL-2024-001');

        $this->get(route('seller.reports'))
            ->assertOk()
            ->assertSee('2,000.00')
            ->assertSee('200.00')
            ->assertSee('Leather Heel');
    }

    public function test_another_seller_cannot_see_or_update_the_order(): void
    {
        [, $product] = $this->sellerWithProduct();
        $this->buyerWhoOrders($product, 1);
        $order = Order::firstOrFail();
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->actingAs($other)->get(route('seller.orders'))->assertOk()->assertDontSee($order->order_number);
        $this->patch(route('seller.orders.status', $order), ['status' => 'confirmed'])->assertForbidden();
        $this->assertSame('placed', $order->fresh()->status);
    }

    public function test_products_page_filters_and_restocks_only_owned_products(): void
    {
        [$seller, $product] = $this->sellerWithProduct(stock: 3);
        $healthy = $this->product($seller, ['name' => 'Canvas Sneaker', 'stock' => 40]);
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $foreign = $this->product($other, ['name' => 'Foreign Boot']);

        $this->actingAs($seller)->get(route('seller.inventory', ['stock' => 'low']))
            ->assertOk()
            ->assertSee('Leather Heel')
            ->assertDontSee('Canvas Sneaker')
            ->assertDontSee('Foreign Boot');
        $this->get(route('seller.inventory', ['q' => 'canvas']))->assertOk()->assertSee('Canvas Sneaker')->assertDontSee('Leather Heel');
        // Inventory only reports stock; restocking happens on the product's edit page.
        $this->get(route('seller.inventory'))->assertOk()
            ->assertDontSee('name="variants[', false)->assertDontSee('type="number"', false)
            ->assertDontSee(route('seller.products.stock', $product))
            ->assertSee(route('seller.products.edit', $product));

        $variant = $product->variants()->firstOrFail();
        $this->patch(route('seller.products.stock', $product), ['variants' => [$variant->id => 25]])->assertSessionHasNoErrors();
        $this->assertSame(25, $variant->fresh()->stock);

        $this->patch(route('seller.products.stock', $product), ['variants' => [$healthy->variants()->first()->id => 1]])
            ->assertSessionHasErrors('variants');
        $this->patch(route('seller.products.stock', $foreign), ['variants' => [$foreign->variants()->first()->id => 0]])
            ->assertForbidden();
    }

    public function test_products_page_lists_the_catalog_and_opens_a_full_editor(): void
    {
        [$seller, $product] = $this->sellerWithProduct();
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $foreign = $this->product($other, ['name' => 'Foreign Boot']);

        $this->actingAs($seller)->get(route('seller.products'))
            ->assertOk()->assertSee('Leather Heel')->assertDontSee('Foreign Boot')
            ->assertSee(route('seller.products.edit', $product));
        $this->get(route('seller.products.create'))->assertOk()->assertSee('Create product');
        $this->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('value="Leather Heel"', false)->assertSee('Manage sale')->assertSee('Delete product');
        $this->get(route('seller.products.edit', $foreign))->assertForbidden();

        $this->post(route('seller.products.store'), [
            'name' => 'Suede Loafer', 'sku' => 'MS-LOAF-01', 'category' => 'shoes', 'description' => 'Soft suede.',
            'price' => 1500, 'stock' => 7, 'low_stock_threshold' => 2, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $created = Product::where('name', 'Suede Loafer')->firstOrFail();
        $this->assertSame('MS-LOAF-01', $created->sku);
        $this->assertSame(7, $created->stock);

        // Products with several variations keep their per-variation stock when details are saved without a stock field.
        $created->variants()->create(['sku' => 'MS-LOAF-02', 'name' => 'Size 9', 'price_minor' => 150000, 'stock' => 3, 'low_stock_threshold' => 1]);
        $this->put(route('seller.products.update', $created), [
            'name' => 'Suede Loafer', 'sku' => 'MS-LOAF-01', 'category' => 'shoes', 'description' => 'Soft Italian suede.',
            'price' => 1500, 'low_stock_threshold' => 2, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Soft Italian suede.', $created->fresh()->description);
        $this->assertSame([7, 3], $created->variants()->orderBy('id')->pluck('stock')->all());

        $this->delete(route('seller.products.destroy', $created))->assertRedirect(route('seller.products'));
    }

    public function test_seller_can_update_store_profile_and_password(): void
    {
        [$seller] = $this->sellerWithProduct();
        $seller->forceFill(['password' => 'Original#Pass1'])->save();

        $this->actingAs($seller)->get(route('seller.account'))->assertOk()->assertSee('Happy Heels')->assertSee(route('addresses.index'));

        $this->patch(route('seller.account.store'), ['store_name' => 'Happy Heels Manila', 'store_description' => 'Handmade heels.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Happy Heels Manila', $seller->sellerShop()->name);
        $this->get(route('seller.dashboard'))->assertSee('Happy Heels Manila');

        $this->patch(route('seller.account.profile'), [
            'name' => 'Maria Santos', 'email' => $seller->email, 'phone' => '09171234567', 'date_of_birth' => '1995-04-02',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Maria Santos', $seller->fresh()->name);

        $this->put(route('seller.account.password'), [
            'current_password' => 'wrong', 'password' => 'Changed#Pass2', 'password_confirmation' => 'Changed#Pass2',
        ])->assertSessionHasErrors('current_password');
        $this->put(route('seller.account.password'), [
            'current_password' => 'Original#Pass1', 'password' => 'Changed#Pass2', 'password_confirmation' => 'Changed#Pass2',
        ])->assertSessionHasNoErrors();
    }

    public function test_reports_reject_an_inverted_date_range(): void
    {
        [$seller] = $this->sellerWithProduct();

        $this->actingAs($seller)->get(route('seller.reports', ['from' => '2026-10-10', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('from');
    }

    private function sellerWithProduct(int $stock = 10): array
    {
        // A throwaway seller first, so the real shop id and user id differ.
        User::factory()->create(['role' => 'seller', 'status' => 'active'])->sellerShop();
        User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'Happy Heels']);

        return [$seller, $this->product($seller, ['stock' => $stock, 'low_stock_threshold' => 5])];
    }

    private function product(User $seller, array $overrides = []): Product
    {
        return Product::create($overrides + [
            'seller_id' => $seller->id, 'name' => 'Leather Heel', 'category' => 'shoes',
            'price' => 1000, 'stock' => 10, 'low_stock_threshold' => 5, 'is_active' => true,
        ]);
    }

    private function buyerWhoOrders(Product $product, int $quantity): User
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $buyer->addresses()->create($this->savedAddressData());
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => $quantity])->assertSessionHasNoErrors();
        $this->post(route('buyer.checkout'), [
            'address_id' => $buyer->addresses()->first()->id, 'payment_method' => 'cod', 'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('buyer.orders'))->assertSessionHasNoErrors();

        return $buyer;
    }

    /**
     * The deployed database keeps the original per-seller order tables alongside the normalized catalog.
     */
    private function useDeployedOrderSchema(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            foreach (['delivery_events', 'shipments', 'order_items', 'payments', 'orders'] as $table) {
                Schema::dropIfExists($table);
            }
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            foreach (['order_number', 'status', 'payment_method', 'recipient_name', 'phone', 'shipping_address'] as $column) {
                $table->string($column);
            }
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_id');
            foreach (['subtotal', 'shipping_fee', 'total', 'shipping_discount'] as $column) {
                $table->decimal($column, 12, 2);
            }
            $table->string('voucher_code')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('placed_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unsignedInteger('shipping_discount_minor')->default(0);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            foreach (['order_id', 'seller_id', 'product_id', 'quantity'] as $column) {
                $table->unsignedBigInteger($column);
            }
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name');
            $table->string('sku');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            foreach (['provider', 'status', 'currency', 'idempotency_key'] as $column) {
                $table->string($column);
            }
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->string('tracking_number')->unique();
            $table->string('status')->default('ready');
            $table->dateTime('picked_up_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('changed_by');
            $table->string('status');
            $table->string('note');
            $table->timestamps();
        });
        Schema::table('cart_items', fn (Blueprint $table) => $table->unsignedBigInteger('product_id')->nullable());
    }
}
