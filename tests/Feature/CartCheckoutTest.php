<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use DatabaseMigrations, PsgcFixtures, LegacyOrderTables, SignsInMobile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
        Schema::table('orders', fn (Blueprint $table) => $table->unsignedInteger('shipping_discount_minor')->default(0));
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->json('payload')->nullable();
        });
        Schema::table('cart_items', fn (Blueprint $table) => $table->unsignedBigInteger('product_id')->nullable());
    }

    public function test_cart_selection_can_remove_multiple_items_without_affecting_other_items(): void
    {
        $buyer = $this->buyer();
        $seller = $this->seller();
        $products = collect([100, 200, 300])->map(fn ($price) => $this->product($seller, $price));
        $products->each(fn ($product) => $this->post(route('buyer.cart.add', $product), ['quantity' => 1]));
        $items = $buyer->cart->items()->orderBy('id')->get();

        $this->get(route('buyer.cart'))->assertOk()->assertSee('Remove selected')->assertDontSee('name="address_id"', false);
        $this->post(route('buyer.cart.selection'), [
            'action' => 'remove', 'cart_item_ids' => [$items[0]->id, $items[2]->id],
        ])->assertRedirect(route('buyer.cart'));

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['id' => $items[1]->id]);
    }

    public function test_selected_checkout_uses_free_shipping_once_and_leaves_unselected_items_in_cart(): void
    {
        $buyer = $this->buyer();
        $sellerOne = $this->seller();
        $sellerTwo = $this->seller();
        $first = $this->product($sellerOne, 100);
        $second = $this->product($sellerTwo, 200);
        $remaining = $this->product($sellerOne, 300);
        foreach ([$first, $second, $remaining] as $product) {
            $this->post(route('buyer.cart.add', $product), ['quantity' => 1]);
        }
        $address = $buyer->addresses()->create($this->savedAddressData());
        $voucher = Voucher::create(['code' => 'ZAYLOSHIP', 'type' => 'free_shipping', 'usage_limit' => 1]);
        $ids = CartItem::whereIn('product_id', [$first->id, $second->id])->pluck('id')->all();

        $this->post(route('buyer.cart.selection'), ['action' => 'checkout', 'cart_item_ids' => $ids])
            ->assertRedirect(route('buyer.checkout.show'));
        $this->get(route('buyer.checkout.show', ['voucher' => 'zayloship']))
            ->assertOk()->assertSee($first->name)->assertSee($second->name)
            ->assertDontSee($remaining->name)->assertSee('free shipping')
            ->assertSee('data-free-shipping-sellers="2"', false);

        $items = $buyer->cart->items()->with(['product', 'variant'])->whereIn('id', $ids)->get();
        foreach (['cod', 'gcash'] as $method) {
            $quote = app(CheckoutService::class)->quote($items, $voucher, paymentMethod: $method);
            $this->assertSame(240.0, $quote['shipping_discount']);
            $this->assertSame(300.0, $quote['total']);
        }

        $this->post(route('buyer.checkout'), [
            'cart_item_ids' => $ids, 'address_id' => $address->id,
            'voucher_code' => 'zayloship', 'idempotency_key' => (string) Str::uuid(),
            'payment_method' => 'cod',
        ])->assertRedirect(route('buyer.orders'));

        $this->assertSame(2, Order::count());
        $this->assertSame(0.0, (float) Order::sum('shipping_fee'));
        $this->assertSame(240, (int) Order::sum('shipping_discount'));
        $this->assertSame(300, (int) Order::sum('total'));
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertDatabaseHas('cart_items', ['product_id' => $remaining->id]);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $first->id]);
    }

    public function test_checkout_rejects_invalid_voucher_without_placing_an_order(): void
    {
        $buyer = $this->buyer();
        $product = $this->product($this->seller(), 100);
        $this->post(route('buyer.cart.add', $product), ['quantity' => 1]);
        $address = $buyer->addresses()->create($this->savedAddressData());

        $this->post(route('buyer.checkout'), [
            'address_id' => $address->id, 'idempotency_key' => (string) Str::uuid(),
            'payment_method' => 'cod', 'voucher_code' => 'DOESNOTEXIST',
        ])->assertSessionHasErrors('voucher_code');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_shop_free_shipping_only_discounts_its_seller_for_cod(): void
    {
        $buyer = $this->buyer();
        $seller = $this->seller();
        foreach ([$this->product($seller, 100), $this->product($this->seller(), 200)] as $product) {
            $this->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        }
        $voucher = Voucher::create(['code' => 'SHOPSHIP', 'type' => 'free_shipping', 'seller_id' => $seller->id]);
        $items = $buyer->cart->items()->with(['product', 'variant'])->get();
        $quote = app(CheckoutService::class)->quote($items, $voucher);
        $this->assertSame(120.0, $quote['shipping_discount']);
        $this->assertSame(420.0, $quote['total']);

        $this->post(route('buyer.cart.selection'), ['action' => 'checkout', 'cart_item_ids' => $items->modelKeys()]);
        $this->get(route('buyer.checkout.show', ['voucher' => $voucher->code]))
            ->assertOk()->assertSee('data-free-shipping-sellers="1"', false);

        $address = $buyer->addresses()->create($this->savedAddressData());
        $this->post(route('buyer.checkout'), [
            'cart_item_ids' => $items->modelKeys(), 'address_id' => $address->id,
            'voucher_code' => $voucher->code, 'idempotency_key' => (string) Str::uuid(),
            'payment_method' => 'cod',
        ])->assertSessionHasNoErrors()->assertRedirect(route('buyer.orders'));

        $this->assertSame(120.0, (float) Order::sum('shipping_fee'));
        $this->assertSame(120.0, (float) Order::sum('shipping_discount'));
        $this->assertSame(420.0, (float) Order::sum('total'));
        foreach (Order::with('payment')->get() as $order) {
            $this->assertSame((float) $order->subtotal + (float) $order->shipping_fee, (float) $order->payment->payload['cod_amount']);
        }
    }

    public function test_buyer_cannot_select_another_buyers_cart_item(): void
    {
        $otherBuyer = $this->buyer();
        $product = $this->product($this->seller(), 100);
        $this->post(route('buyer.cart.add', $product), ['quantity' => 1]);
        $otherItem = $otherBuyer->cart->items()->firstOrFail();

        $this->actingAs($this->buyer())->post(route('buyer.cart.selection'), [
            'action' => 'remove', 'cart_item_ids' => [$otherItem->id],
        ])->assertSessionHasErrors('cart_item_ids.0');

        $this->assertDatabaseHas('cart_items', ['id' => $otherItem->id]);
    }

    public static function shippingFunders(): array
    {
        $cases = [];
        foreach (['cod', 'gcash'] as $method) {
            $cases[$method.' no voucher'] = ['buyer', 800.0, 100.0, 1120.0, $method];
            $cases[$method.' seller voucher'] = ['seller', 680.0, 100.0, 1000.0, $method];
            $cases[$method.' admin voucher'] = ['admin', 800.0, -20.0, 1000.0, $method];
        }

        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shippingFunders')]
    public function test_checkout_snapshots_the_correct_shipping_funder(string $funder, float $sellerNet, float $adminNet, float $buyerTotal, string $method): void
    {
        $buyer = $this->buyer();
        $seller = $this->seller();
        $product = $this->product($seller, 1000);
        $this->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $items = $buyer->cart->items()->get();
        $address = $buyer->addresses()->create($this->savedAddressData());
        $voucher = $funder === 'buyer' ? null : Voucher::create([
            'code' => 'FUNDING-TEST', 'type' => 'free_shipping', 'is_active' => true,
            'funded_by' => $funder === 'admin' ? 'zaylo' : 'seller',
            'seller_id' => $funder === 'seller' ? $product->seller_id : null,
        ]);
        $this->post(route('buyer.checkout'), [
            'cart_item_ids' => $items->modelKeys(), 'address_id' => $address->id,
            'idempotency_key' => (string) Str::uuid(), 'payment_method' => $method, 'voucher_code' => $voucher?->code,
        ])->assertSessionHasNoErrors()->assertRedirect(route('buyer.orders'));
        $order = Order::with('payment')->sole();
        $split = app(\App\Services\Settlement::class)->forOrder($order);
        $this->assertSame($buyerTotal, $order->total);
        $this->assertSame($funder, $split['shipping_funded_by']);
        $this->assertEquals($sellerNet, $split['seller_earnings']);
        $this->assertEquals($adminNet, $split['admin_earnings']);
        $this->assertEquals(100, $split['admin_product_commission']);
        if ($voucher) {
            $voucher->update(['funded_by' => $funder === 'seller' ? 'zaylo' : 'seller']);
            $this->assertEquals($sellerNet, app(\App\Services\Settlement::class)->forOrder($order->fresh())['seller_earnings']);
        }
        if ($method === 'gcash') {
            $order->update(['status' => 'delivered']);
            $this->actingAs($seller)->get(route('seller.settlement'))->assertOk()
                ->assertSee('GCash sales proceeds')->assertSee('Fees to remit')
                ->assertSee('&#8369;'.number_format($sellerNet, 2), false);
        }
    }

    public function test_buyer_cannot_assign_logistics_and_seller_selects_the_registered_partner(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('logistics_provider_id')->nullable();
            $table->string('shipping_zone')->nullable();
            $table->string('courier_choice')->nullable();
            $table->json('settlement_breakdown')->nullable();
        });
        Schema::table('shipments', fn (Blueprint $table) => $table->unsignedBigInteger('logistics_provider_id')->nullable());
        $providers = collect(['Buyer choice', 'Seller choice'])->map(fn ($name) => \App\Models\LogisticsProvider::create([
            'user_id' => User::factory()->create(['role' => 'logistics', 'status' => 'active'])->id,
            'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved', 'rider_rate_minor' => 6555,
        ]));
        $buyer = $this->buyer();
        $seller = $this->seller();
        $product = $this->product($seller, 1000);
        $this->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $item = $buyer->cart()->firstOrFail()->items()->firstOrFail();
        $address = $buyer->addresses()->create($this->savedAddressData());
        $this->withSession(['checkout_cart_item_ids' => [$item->id]])->get(route('buyer.checkout.show'))->assertOk()
            ->assertSee('Each seller chooses an approved ZAYLO logistics partner')
            ->assertDontSee('name="logistics_provider_id"', false)->assertDontSee('name="courier_choice"', false);
        $this->post(route('buyer.checkout'), [
            'cart_item_ids' => [$item->id], 'address_id' => $address->id, 'idempotency_key' => (string) Str::uuid(),
            'logistics_provider_id' => $providers[0]->id, 'courier_choice' => 'flash_express',
        ])->assertSessionHasNoErrors();
        $order = Order::with('payment')->sole();
        $this->assertNull($order->logistics_provider_id);
        $this->assertNull($order->courier_choice);
        $this->assertEquals(120, $order->shipping_fee);
        $this->assertEquals(1120, $order->total);
        $this->assertSame('seller', $order->settlement_breakdown['logistics_selection']);
        $order->update(['status' => 'processing']);

        $providers[0]->update(['status' => 'suspended']);
        $this->actingAs($seller)->post(route('seller.pickups.schedule'), [
            'order_id' => $order->id, 'logistics_provider_id' => $providers[0]->id,
        ])->assertSessionHasErrors('logistics_provider_id');
        $this->patch(route('seller.orders.status', $order), [
            'status' => 'ready_for_pickup', 'logistics_provider_id' => $providers[0]->id,
        ])->assertSessionHasErrors('logistics_provider_id');
        $this->patch(route('seller.orders.status', $order), [
            'status' => 'ready_for_pickup', 'logistics_provider_id' => $providers[1]->id,
        ])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertEquals($providers[1]->id, $order->logistics_provider_id);
        $this->assertEquals($providers[1]->id, $order->shipment->logistics_provider_id);
        $this->assertEquals(65.55, $order->settlement_breakdown['rider_earnings']);
        $this->assertEquals(65.55, $order->payment->payload['commission_breakdown']['rider_earnings']);
        $this->assertEquals(1120, $order->total);
        $this->assertEquals(1120, $order->payment->amount);
        $this->assertEquals(800, $order->settlement_breakdown['seller_earnings']);
        $this->assertSame('Seller choice', $order->courierName());
        $providers[1]->update(['rider_rate_minor' => 5000]);
        $this->assertEquals(65.55, $order->fresh()->settlement_breakdown['rider_earnings']);
    }

    public function test_mobile_checkout_cannot_choose_a_partner_even_when_an_old_client_sends_one(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('logistics_provider_id')->nullable();
            $table->string('shipping_zone')->nullable();
            $table->string('courier_choice')->nullable();
        });
        Schema::table('shipments', fn (Blueprint $table) => $table->unsignedBigInteger('logistics_provider_id')->nullable());
        $partner = \App\Models\LogisticsProvider::create([
            'user_id' => User::factory()->create(['role' => 'logistics', 'status' => 'active'])->id,
            'name' => 'Registered partner', 'slug' => 'registered-partner', 'status' => 'approved',
        ]);
        $buyer = $this->buyer();
        $product = $this->product($this->seller(), 1000);
        $this->post(route('buyer.cart.add', $product), ['quantity' => 1]);
        $item = $buyer->cart()->firstOrFail()->items()->firstOrFail();
        $address = $buyer->addresses()->create($this->savedAddressData());
        $this->signInMobile($buyer)->postJson('/api/mobile/checkout/quote', ['cart_item_ids' => [$item->id]])->assertOk()
            ->assertJsonCount(0, 'logistics_partners')->assertJsonCount(0, 'couriers')
            ->assertJsonPath('default_logistics_provider_id', null)->assertJsonPath('logistics_selected_by', 'seller')
            ->assertJsonPath('totals.shipping', 120);
        $this->postJson('/api/mobile/checkout', [
            'cart_item_ids' => [$item->id], 'address_id' => $address->id, 'idempotency_key' => (string) Str::uuid(),
            'logistics_provider_id' => $partner->id, 'courier_choice' => 'jnt_express',
        ])->assertCreated();
        $order = Order::sole();
        $this->assertNull($order->logistics_provider_id);
        $this->assertNull($order->courier_choice);
        $this->assertEquals(1120, $order->total);
    }

    private function buyer(): User
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer);

        return $buyer;
    }

    private function seller(): User
    {
        return User::factory()->create(['role' => 'seller', 'status' => 'active']);
    }

    private function product(User $seller, int $price): Product
    {
        return Product::create([
            'seller_id' => $seller->id, 'name' => 'Product '.$price.' '.Str::random(4),
            'category' => 'blenders', 'price' => $price, 'stock' => 5, 'is_active' => true,
        ]);
    }
}
