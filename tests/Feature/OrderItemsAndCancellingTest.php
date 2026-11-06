<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Orders list every checked-out product with its photo and a link to it, and a buyer can cancel only until the
 * seller confirms the order, even from a page opened before the seller did.
 */
class OrderItemsAndCancellingTest extends TestCase
{
    use LegacyOrderTables, RefreshDatabase, SignsInMobile;

    private User $seller;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacyOrderTables();
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
    }

    public function test_buyer_and_seller_orders_show_each_checked_out_product_with_a_link_to_it(): void
    {
        $lamp = $this->product('Rattan Lamp');
        $gone = $this->product('Old Basket');
        $order = $this->order('placed', [[$lamp, 2, 450], [$gone, 1, 300]]);
        $gone->update(['is_active' => false]);

        $this->actingAs($this->buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee('class="order-item"', false)
            ->assertSee('href="'.route('products.show', $lamp).'" class="order-item__name"', false)
            ->assertSee('2 &times; &#8369;450.00', false)
            ->assertSee('Old Basket')->assertSee('No longer on sale')
            ->assertDontSee('href="'.route('products.show', $gone).'"', false);

        // The seller sees every product of the order in the table, not just the first one.
        $this->actingAs($this->seller)->get(route('seller.orders'))->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('href="'.route('products.show', $lamp).'" class="order-item__name"', false)
            ->assertSee('Old Basket')
            ->assertDontSee('+ 1 more');
    }

    public function test_buyers_cannot_cancel_once_the_seller_confirms_even_from_an_old_page(): void
    {
        $product = $this->product('Rattan Lamp', stock: 3);
        $waiting = $this->order('placed', [[$product, 1, 450]]);
        $order = $this->order('placed', [[$product, 2, 450]]);

        // The buyer opens My Orders while the seller has not confirmed yet: Cancel is offered.
        $this->actingAs($this->buyer)->get(route('buyer.orders'))->assertOk()
            ->assertSee(route('buyer.orders.cancel', $order), false);

        // Meanwhile the seller confirms it.
        $this->actingAs($this->seller)->patch(route('seller.orders.status', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();

        // Pressing Cancel on that old page is refused with the reason, and nothing changes.
        $this->actingAs($this->buyer)->from(route('buyer.orders'))->post(route('buyer.orders.cancel', $order))
            ->assertRedirect(route('buyer.orders'))
            ->assertSessionHasErrors(['order' => "The seller already confirmed order {$order->order_number}, so it can no longer be cancelled. Message the seller if something is wrong."]);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(3, (int) $product->fresh()->stock);

        // After a refresh the button is gone and the page says why.
        $this->get(route('buyer.orders'))->assertOk()
            ->assertDontSee(route('buyer.orders.cancel', $order), false)
            ->assertSee('The seller confirmed this order, so it can no longer be cancelled.');

        // An order the seller has not confirmed can still be cancelled. (Its line is unlinked from the product here:
        // these test tables pair the deployed order columns with the newer product table, which has no stock column.)
        DB::table('order_items')->where('order_id', $waiting->id)->update(['product_id' => null]);
        $this->post(route('buyer.orders.cancel', $waiting))->assertSessionHasNoErrors()->assertSessionHas('status', 'Order cancelled and stock restored.');
        $this->assertSame('cancelled', $waiting->fresh()->status);
        $this->post(route('buyer.orders.cancel', $waiting))->assertSessionHasErrors(['order' => "Order {$waiting->order_number} is already cancelled."]);

        // The app hides Cancel too, and gets the same answer from an old screen.
        $this->signInMobile($this->buyer);
        $this->getJson("/api/mobile/orders/{$order->id}")->assertOk()->assertJsonPath('order.can_cancel', false);
        $this->postJson("/api/mobile/orders/{$order->id}/cancel")->assertUnprocessable()
            ->assertJsonPath('errors.order.0', "The seller already confirmed order {$order->order_number}, so it can no longer be cancelled. Message the seller if something is wrong.");
        $placed = $this->order('placed', [[$product, 1, 450]]);
        $this->getJson("/api/mobile/orders/{$placed->id}")->assertOk()->assertJsonPath('order.can_cancel', true);
    }

    private function product(string $name, int $stock = 10): Product
    {
        return Product::create([
            'seller_id' => $this->seller->sellerShop()->id, 'name' => $name, 'category' => 'storage_bins',
            'price' => 450, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    /** @param  array<int, array{0: Product, 1: int, 2: float}>  $items */
    private function order(string $status, array $items): Order
    {
        $subtotal = collect($items)->sum(fn ($item) => $item[1] * $item[2]);
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ZAY-'.strtoupper(uniqid()), 'user_id' => $this->buyer->id, 'seller_id' => $this->seller->sellerShop()->id,
            'status' => $status, 'subtotal' => $subtotal, 'shipping_fee' => 49, 'total' => $subtotal + 49, 'payment_method' => 'cod',
            'recipient_name' => $this->buyer->name, 'phone' => '09171234567', 'shipping_address' => '12 Rizal St, Calamba, Laguna',
            'placed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert(['order_id' => $orderId, 'provider' => 'cod', 'status' => 'pending', 'amount' => $subtotal + 49, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($items as [$product, $quantity, $price]) {
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => $price,
                'quantity' => $quantity, 'line_total' => $quantity * $price, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return Order::findOrFail($orderId);
    }
}
