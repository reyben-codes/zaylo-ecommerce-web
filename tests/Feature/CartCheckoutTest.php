<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase, PsgcFixtures;

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
            ->assertDontSee($remaining->name)->assertSee('free shipping');

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
            'category' => 'electronics', 'price' => $price, 'stock' => 5, 'is_active' => true,
        ]);
    }
}
