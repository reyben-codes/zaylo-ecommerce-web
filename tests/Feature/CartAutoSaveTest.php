<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAutoSaveTest extends TestCase
{
    use RefreshDatabase;

    private function item(User $buyer, int $stock = 10): CartItem
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create([
            'seller_id' => $seller->id, 'name' => 'Cart item', 'category' => 'blenders',
            'price' => 100, 'stock' => $stock, 'is_active' => true,
        ]);

        return app(CartService::class)->add($buyer, $product, 1);
    }

    public function test_auto_save_returns_persisted_quantities_prices_and_cart_count(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $first = $this->item($buyer);
        $second = $this->item($buyer);
        $untouched = $this->item($buyer);

        $this->actingAs($buyer)->patchJson(route('buyer.cart.update-all'), [
            'quantities' => [$first->id => 3, $second->id => 2],
        ])->assertOk()->assertJsonPath('cart_quantity', 6)
            ->assertJsonCount(2, 'items')
            ->assertJsonFragment(['id' => $first->id, 'quantity' => 3, 'unit_price' => 100, 'line_total' => 300, 'available_stock' => 10]);

        $this->assertSame(3, $first->fresh()->quantity);
        $this->assertSame(2, $second->fresh()->quantity);
        $this->assertSame(1, $untouched->fresh()->quantity);
        $this->get(route('buyer.cart'))->assertOk()->assertSee('js/cart-autosave.js', false)->assertDontSee('Update all');
    }

    public function test_stock_failure_leaves_the_entire_batch_unchanged(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $first = $this->item($buyer);
        $second = $this->item($buyer, 2);

        $this->actingAs($buyer)->patchJson(route('buyer.cart.update-all'), [
            'quantities' => [$first->id => 3, $second->id => 3],
        ])->assertUnprocessable()->assertJsonValidationErrors('quantities.'.$second->id);
        $this->assertSame(1, $first->fresh()->quantity);
        $this->assertSame(1, $second->fresh()->quantity);
    }

    public function test_buyers_cannot_update_another_buyers_items(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $own = $this->item($buyer);
        $other = $this->item(User::factory()->create(['role' => 'buyer', 'status' => 'active']));

        $this->actingAs($buyer)->patchJson(route('buyer.cart.update-all'), [
            'quantities' => [$own->id => 2, $other->id => 2],
        ])->assertUnprocessable()->assertJsonValidationErrors('quantities');
        $this->assertSame(1, $own->fresh()->quantity);
        $this->assertSame(1, $other->fresh()->quantity);
    }
}
