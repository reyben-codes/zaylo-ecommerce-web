<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The only limit on how many of an item a buyer can take is the stock (there used to be a fixed cap of 99).
 */
class CartQuantityLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyers_can_take_the_whole_stock_even_above_99(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Bulk Socks', 'category' => 'clothing', 'price' => 50, 'stock' => 100, 'is_active' => true]);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $this->actingAs($buyer)->get(route('products.show', $product))->assertOk()
            ->assertSee('name="quantity" min="1" max="100"', false);

        $this->post(route('buyer.cart.add', $product), ['quantity' => 101])->assertSessionHasErrors(['quantity' => 'Only 100 item(s) are available.']);
        $this->post(route('buyer.cart.add', $product), ['quantity' => 100])->assertSessionHasNoErrors();
        $this->assertSame(100, (int) \App\Models\CartItem::sole()->quantity);
    }
}
