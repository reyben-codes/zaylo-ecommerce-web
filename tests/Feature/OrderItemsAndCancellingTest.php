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
        // The deployed orders table gets the cancel reason columns from this migration.
        (require database_path('migrations/2026_11_07_000006_add_cancellation_details_to_orders.php'))->up();
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
        $this->actingAs($this->buyer)->from(route('buyer.orders'))->post(route('buyer.orders.cancel', $order), ['reason' => 'found_cheaper'])
            ->assertRedirect(route('buyer.orders'))
            ->assertSessionHasErrors(['order' => "The seller already confirmed order {$order->order_number}, so it can no longer be cancelled. Message the seller if something is wrong."]);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(3, (int) $product->fresh()->stock);

        // After a refresh Cancel is shown disabled, with the reason, and cannot be sent.
        $this->get(route('buyer.orders'))->assertOk()
            ->assertDontSee(route('buyer.orders.cancel', $order), false)
            ->assertSee('class="text-button danger order-cancel--locked" type="button" disabled', false)
            ->assertSee('The seller already confirmed this order and is preparing it, so it can no longer be cancelled.');

        // An order the seller has not confirmed can still be cancelled. (Its line is unlinked from the product here:
        // these test tables pair the deployed order columns with the newer product table, which has no stock column.)
        DB::table('order_items')->where('order_id', $waiting->id)->update(['product_id' => null]);
        $this->post(route('buyer.orders.cancel', $waiting), ['reason' => 'ordered_by_mistake'])->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Order cancelled and stock restored. You can buy it again anytime from Cancelled.');
        $this->assertSame('cancelled', $waiting->fresh()->status);
        $this->post(route('buyer.orders.cancel', $waiting), ['reason' => 'ordered_by_mistake'])->assertSessionHasErrors(['order' => "Order {$waiting->order_number} is already cancelled."]);

        // The app hides Cancel too, and gets the same answer from an old screen.
        $this->signInMobile($this->buyer);
        $this->getJson("/api/mobile/orders/{$order->id}")->assertOk()->assertJsonPath('order.can_cancel', false)
            ->assertJsonPath('order.cancel_locked_reason', 'The seller already confirmed this order and is preparing it, so it can no longer be cancelled. Message the seller if something is wrong.');
        $this->postJson("/api/mobile/orders/{$order->id}/cancel", ['reason' => 'found_cheaper'])->assertUnprocessable()
            ->assertJsonPath('errors.order.0', "The seller already confirmed order {$order->order_number}, so it can no longer be cancelled. Message the seller if something is wrong.");
        $placed = $this->order('placed', [[$product, 1, 450]]);
        $this->getJson("/api/mobile/orders/{$placed->id}")->assertOk()->assertJsonPath('order.can_cancel', true);
    }

    public function test_buyers_give_a_reason_when_cancelling_and_can_buy_again(): void
    {
        $lamp = $this->product('Rattan Lamp');
        $order = $this->order('placed', [[$lamp, 2, 450]]);
        $this->actingAs($this->buyer);

        // The reasons are offered; one is required, and "Other" needs a few words.
        $this->get(route('buyer.orders'))->assertOk()->assertSee('Why are you cancelling?')->assertSee('I found a cheaper price elsewhere');
        $this->post(route('buyer.orders.cancel', $order))->assertSessionHasErrors(['reason' => 'Choose why you are cancelling.']);
        $this->post(route('buyer.orders.cancel', $order), ['reason' => 'other'])->assertSessionHasErrors(['note' => 'Tell us the reason in a few words.']);
        $this->assertSame('placed', $order->fresh()->status);

        // (The order line is unlinked while cancelling: these test tables have no product stock column to restore.)
        DB::table('order_items')->where('order_id', $order->id)->update(['product_id' => null]);
        $this->post(route('buyer.orders.cancel', $order), ['reason' => 'change_address', 'note' => 'Moving next week'])->assertSessionHasNoErrors();
        DB::table('order_items')->where('order_id', $order->id)->update(['product_id' => $lamp->id]);
        $order->refresh();
        $this->assertSame(['change_address', 'Moving next week'], [$order->cancel_reason, $order->cancel_note]);
        $this->assertNotNull($order->cancelled_at);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'status' => 'cancelled', 'note' => 'Cancelled by buyer: I need to change the delivery address (Moving next week).']);

        // Cancelled shows the reason and Buy again, which puts the products back in the cart.
        $this->get(route('buyer.orders', ['status' => 'cancelled']))->assertOk()
            ->assertSee($order->order_number)->assertSee('I need to change the delivery address')
            ->assertSee(route('buyer.orders.buy-again', $order), false)->assertSee('Moves to Purchase history on');
        $this->post(route('buyer.orders.buy-again', $order))->assertRedirect(route('buyer.cart'))
            ->assertSessionHas('status', '1 product was added to your cart.');
        $this->assertSame(2, (int) $this->buyer->cart()->first()->items()->sum('quantity'));

        // A product that is no longer on sale is skipped and named.
        $lamp->update(['is_active' => false]);
        $this->post(route('buyer.orders.buy-again', $order))->assertSessionHas('error', 'None of these products can be bought again right now: they are sold out or no longer on sale.');

        // After two days it moves from Cancelled to Purchase history.
        $this->travel(3)->days();
        $this->get(route('buyer.orders', ['status' => 'cancelled']))->assertDontSee($order->order_number);
        $this->get(route('buyer.orders', ['status' => 'history']))->assertOk()->assertSee($order->order_number)->assertSee('Purchase history');

        // The app gets the same tabs, reasons and flags.
        $this->signInMobile($this->buyer);
        $this->getJson('/api/mobile/orders?status=history')->assertOk()
            ->assertJsonPath('counts.history', 1)->assertJsonPath('counts.cancelled', 0)
            ->assertJsonPath('cancel_reasons.0', ['key' => 'change_address', 'label' => 'I need to change the delivery address'])
            ->assertJsonPath('orders.data.0.cancel_reason', 'I need to change the delivery address')
            ->assertJsonPath('orders.data.0.can_buy_again', true);
    }

    public function test_delivered_orders_offer_to_review_and_the_app_cancels_with_a_reason(): void
    {
        $lamp = $this->product('Rattan Lamp');
        $delivered = $this->order('delivered', [[$lamp, 1, 450]]);
        $placed = $this->order('placed', [[$lamp, 1, 450]]);
        DB::table('order_items')->where('order_id', $placed->id)->update(['product_id' => null]);
        $item = $delivered->items()->firstOrFail();

        $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'delivered']))->assertOk()
            ->assertSee(route('buyer.reviews.create', $item), false)->assertSee('To review')
            ->assertSee(route('buyer.orders.buy-again', $delivered), false);

        $this->signInMobile($this->buyer);
        $listed = collect($this->getJson('/api/mobile/orders')->assertOk()->json('orders.data'))->keyBy('id');
        $this->assertTrue($listed[$delivered->id]['can_review']);
        $this->assertTrue($listed[$delivered->id]['items'][0]['can_review']);
        $this->assertTrue($listed[$delivered->id]['can_confirm_receipt']);
        $this->assertFalse($listed[$placed->id]['can_review']);
        $this->postJson("/api/mobile/orders/{$placed->id}/cancel", ['reason' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/mobile/orders/{$placed->id}/cancel", ['reason' => 'too_long'])->assertOk()
            ->assertJsonPath('order.status', 'cancelled')->assertJsonPath('order.cancel_reason', 'It is taking too long to ship');
        $this->postJson("/api/mobile/orders/{$delivered->id}/buy-again")->assertOk()->assertJsonPath('added', ['Rattan Lamp']);
    }

    private function product(string $name, int $stock = 10): Product
    {
        return Product::create([
            'seller_id' => $this->seller->id, 'name' => $name, 'category' => 'storage_bins',
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
