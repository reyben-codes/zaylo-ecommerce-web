<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAnimationTest extends TestCase
{
    use RefreshDatabase;

    public function test_animated_add_confirms_saved_item_and_reports_stock_errors(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Animation test', 'category' => 'shoes', 'price' => 100, 'stock' => 2, 'is_active' => true]);
        $this->actingAs($buyer)->postJson(route('buyer.cart.add', $product), ['quantity' => 1])
            ->assertOk()->assertJson(['message' => 'Product added to your cart.', 'cart_url' => route('buyer.cart'), 'cart_quantity' => 1]);
        $this->assertSame(1, (int) $buyer->cart->items()->sum('quantity'));
        $this->postJson(route('buyer.cart.add', $product), ['quantity' => 2])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(1, (int) $buyer->cart->items()->sum('quantity'));
        $this->post(route('buyer.cart.add', $product), ['quantity' => 1, 'purchase_action' => 'buy_now'])
            ->assertRedirect(route('buyer.checkout.show'))->assertSessionHas('checkout_cart_item_ids');
    }

    public function test_repeated_adds_update_count_and_non_javascript_add_returns_to_product(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Keep shopping', 'category' => 'shoes', 'price' => 100, 'stock' => 10, 'is_active' => true]);
        $this->actingAs($buyer);
        foreach ([1, 2] as $count) {
            $this->postJson(route('buyer.cart.add', $product), ['quantity' => 1])->assertOk()->assertJsonPath('cart_quantity', $count);
        }
        $this->from(route('products.show', $product))->post(route('buyer.cart.add', $product), ['quantity' => 1])
            ->assertRedirect(route('products.show', $product))->assertSessionHas('status', 'Product added to your cart.');
        $this->assertSame(3, (int) $buyer->cart->items()->sum('quantity'));
    }
}
