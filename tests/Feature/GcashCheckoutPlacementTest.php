<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\LegacyOrderTables;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class GcashCheckoutPlacementTest extends TestCase
{
    use RefreshDatabase, LegacyOrderTables, PsgcFixtures;

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
        return Product::create(['seller_id' => $seller->id, 'name' => 'Checkout item',
            'category' => 'blenders', 'price' => $price, 'stock' => 5, 'is_active' => true]);
    }

    public function test_gcash_checkout_is_not_placed_until_payment_is_confirmed(): void
    {
        $buyer = $this->buyer();
        $seller = $this->seller();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        config(['escrow.demo_enabled' => true, 'escrow.live' => false, 'escrow.admin_user_id' => $admin->id]);
        $product = $this->product($seller, 1000);
        $address = $buyer->addresses()->create($this->savedAddressData());
        $this->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $key = (string) Str::uuid();
        $input = ['address_id' => $address->id, 'payment_method' => 'gcash', 'idempotency_key' => $key];
        $this->post(route('buyer.checkout'), $input)->assertSessionHasNoErrors()->assertRedirect();
        $draft = Order::firstOrFail();
        $this->assertSame('awaiting_payment', $draft->status);
        $this->assertNull($draft->placed_at);
        $this->assertDatabaseMissing('order_status_histories', ['order_id' => $draft->id, 'status' => 'placed']);
        $this->assertSame(1, \App\Support\BuyerOrderTabs::apply(Order::query(), 'to_pay')->count());
        $this->assertSame(0, \App\Support\BuyerOrderTabs::apply(Order::query(), 'to_ship')->count());
        $this->actingAs($seller)->get(route('seller.orders'))->assertOk()->assertDontSee($draft->order_number);
        $escrow = \App\Services\EscrowPayments::forOrder($draft);
        $payments = app(\App\Services\DemoPayments::class);
        $payments->authorize($escrow, 'decline');
        $this->assertSame('awaiting_payment', $draft->fresh()->status);
        $payments->authorize($escrow, 'approve');
        $this->assertSame('placed', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->placed_at);
        $this->assertSame(0, \App\Support\BuyerOrderTabs::apply(Order::query(), 'to_pay')->count());
        $this->assertSame(1, \App\Support\BuyerOrderTabs::apply(Order::query(), 'to_ship')->count());
        $this->actingAs($seller)->get(route('seller.orders'))->assertOk()->assertSee($draft->order_number);
        $payments->authorize($escrow, 'approve');
        $this->assertSame(1, Order::count());
        $this->assertEquals(4, $product->fresh()->stock);
    }

}
