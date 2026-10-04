<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class ScheduledSaleCheckoutTest extends TestCase
{
    use DatabaseMigrations, PsgcFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Isolated SQLite fixture for the existing deployed checkout/order schema.
        Schema::withoutForeignKeyConstraints(function () {
            foreach (['order_items', 'payments', 'orders'] as $table) {
                Schema::drop($table);
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
            $table->timestamps();
            $table->unsignedInteger('shipping_discount_minor')->default(0);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            foreach (['order_id', 'seller_id', 'product_id', 'product_variant_id', 'quantity'] as $column) {
                $table->unsignedBigInteger($column);
            }
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

    public function test_checkout_uses_current_sale_and_order_keeps_price_after_expiry(): void
    {
        [$product, $buyer] = $this->setupPurchase();
        $this->travelTo('2026-10-11');
        $this->placeOrder($buyer);
        $order = Order::firstOrFail();
        $this->assertSame(1600.0, (float) $order->subtotal);
        $this->assertSame(1720.0, $order->total);
        $this->assertSame(800.0, $order->items()->first()->unit_price);
        $this->assertSame(1720.0, $order->payment->amount);
        $this->assertSame(8, $product->fresh()->stock);
        $this->travelTo('2026-10-16');
        $this->assertSame('1000.00', $product->fresh()->price);
        $this->assertSame(800.0, $order->items()->first()->unit_price);
    }

    public function test_checkout_recalculates_expired_sale_instead_of_trusting_submitted_price(): void
    {
        [, $buyer] = $this->setupPurchase();
        $this->travelTo('2026-10-15 00:00:00');
        $item = $buyer->cart->items()->firstOrFail();
        $this->post(route('buyer.checkout'), ['address_id' => $buyer->addresses()->first()->id, 'idempotency_key' => (string) Str::uuid(), 'quoted_unit_prices' => [$item->id => '800.00']])
            ->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $item->variant->stock);
        $this->placeOrder($buyer);
        $order = Order::firstOrFail();
        $this->assertSame(2000.0, (float) $order->subtotal);
        $this->assertSame(1000.0, $order->items()->first()->unit_price);
    }

    private function setupPurchase(): array
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Sale checkout shoe', 'category' => 'shoes', 'price' => 1000, 'stock' => 10, 'is_active' => true,
            'sale_type' => 'percent', 'sale_value' => 20, 'sale_starts_at' => '2026-10-10 00:00:00', 'sale_ends_at' => '2026-10-15 00:00:00']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $buyer->addresses()->create($this->savedAddressData());
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => 2])->assertSessionHasNoErrors();

        return [$product, $buyer];
    }

    private function placeOrder(User $buyer): void
    {
        $this->post(route('buyer.checkout'), ['address_id' => $buyer->addresses()->first()->id, 'payment_method' => 'cod', 'idempotency_key' => (string) Str::uuid(), 'unit_price' => 1])
            ->assertRedirect(route('buyer.orders'))->assertSessionHasNoErrors();
    }
}
