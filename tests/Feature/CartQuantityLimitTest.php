<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The only limit on how many of an item a buyer can take is the stock (there used to be a fixed cap of 99).
 * Above Zaylo's warehouse capacity the buyer is told the order ships from the seller's warehouse.
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

    public function test_the_warehouse_note_shows_only_above_capacity(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Bulk Socks', 'category' => 'clothing', 'price' => 50, 'stock' => 300, 'is_active' => true]);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $note = "Zaylo's warehouse can only hold 100 of this item";

        $this->actingAs($buyer)->get(route('products.show', $product))->assertOk()
            ->assertSee('data-warehouse-note="product-quantity"', false)->assertSee('max="300"', false);

        $this->post(route('buyer.cart.add', $product), ['quantity' => 100]);
        $hiddenNote = '/data-warehouse-note="quantity-\d+"[^>]*\shidden\s*>/';
        $page = $this->get(route('buyer.cart'))->assertOk()->assertSee($note, false)->getContent();
        $this->assertMatchesRegularExpression($hiddenNote, $page);

        $this->post(route('buyer.cart.add', $product), ['quantity' => 150]);
        $this->assertDoesNotMatchRegularExpression($hiddenNote, $this->get(route('buyer.cart'))->assertOk()->getContent());
    }

    public function test_the_mobile_cart_accepts_the_whole_stock_even_above_99(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Bulk Socks', 'category' => 'clothing', 'price' => 50, 'stock' => 250, 'is_active' => true]);
        $token = User::factory()->create(['role' => 'buyer', 'status' => 'active'])->createToken('test');

        $this->withToken($token)->postJson('/api/mobile/cart/'.$product->slug, ['quantity' => 251])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $item = $this->withToken($token)->postJson('/api/mobile/cart/'.$product->slug, ['quantity' => 120])->assertCreated()->json('added_item_id');
        $this->withToken($token)->patchJson('/api/mobile/cart/items/'.$item, ['quantity' => 250])->assertOk();
        $this->assertSame(250, (int) \App\Models\CartItem::sole()->quantity);
    }
}
