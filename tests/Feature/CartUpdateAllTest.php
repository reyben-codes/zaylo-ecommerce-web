<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartUpdateAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_all_saves_every_changed_quantity_at_once(): void
    {
        [$buyer, $lamp, $mug] = $this->cartWith(['Desk Lamp' => 10, 'Coffee Mug' => 10]);

        $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk()
            ->assertSee('id="cart-update-all"', false)->assertSee('Update all')
            ->assertSee('data-cart-item-id="'.$lamp->id.'"', false);

        $this->from(route('buyer.cart'))->patch(route('buyer.cart.update-all'), ['quantities' => [$lamp->id => 3, $mug->id => 5]])
            ->assertRedirect(route('buyer.cart'))->assertSessionHasNoErrors()->assertSessionHas('status', '2 items updated.');

        $this->assertSame(3, $lamp->fresh()->quantity);
        $this->assertSame(5, $mug->fresh()->quantity);
    }

    public function test_nothing_is_saved_when_any_quantity_exceeds_stock(): void
    {
        [$buyer, $lamp, $mug] = $this->cartWith(['Desk Lamp' => 10, 'Coffee Mug' => 2]);

        $this->actingAs($buyer)->from(route('buyer.cart'))
            ->patch(route('buyer.cart.update-all'), ['quantities' => [$lamp->id => 4, $mug->id => 6]])
            ->assertSessionHasErrors(["quantities.{$mug->id}" => 'Coffee Mug: only 2 left in stock.']);

        $this->assertSame(1, $lamp->fresh()->quantity);
        $this->assertSame(1, $mug->fresh()->quantity);
        $this->get(route('buyer.cart'))->assertSee('Coffee Mug: only 2 left in stock.');
    }

    public function test_other_buyers_items_and_invalid_quantities_are_rejected(): void
    {
        [$buyer, $lamp] = $this->cartWith(['Desk Lamp' => 10]);
        [$other, $theirs] = $this->cartWith(['Their Vase' => 10]);

        $this->actingAs($buyer)->patch(route('buyer.cart.update-all'), ['quantities' => [$theirs->id => 2]])->assertSessionHasErrors('quantities');
        $this->patch(route('buyer.cart.update-all'), ['quantities' => [$lamp->id => 0]])->assertSessionHasErrors("quantities.{$lamp->id}");
        $this->patch(route('buyer.cart.update-all'), [])->assertSessionHasErrors('quantities');

        $this->assertSame(1, $theirs->fresh()->quantity);
        $this->assertSame(1, $lamp->fresh()->quantity);
    }

    /**
     * Returns the buyer followed by one cart item per product (name => stock), each with quantity 1.
     */
    private function cartWith(array $products): array
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $items = [];
        foreach ($products as $name => $stock) {
            $product = Product::create(['seller_id' => $seller->id, 'name' => $name, 'category' => 'home_living', 'price' => 300, 'stock' => $stock, 'is_active' => true]);
            $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
            $items[] = CartItem::whereHas('cart', fn ($cart) => $cart->where('user_id', $buyer->id))->latest('id')->firstOrFail();
        }

        return [$buyer, ...$items];
    }
}
