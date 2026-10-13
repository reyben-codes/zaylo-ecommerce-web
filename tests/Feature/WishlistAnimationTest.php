<?php

namespace Tests\Feature;

use App\Models\{Product, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistAnimationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_animation_response_matches_saved_state_and_user_count(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Wishlist shoe', 'category' => 'shoes', 'price' => 100, 'stock' => 5, 'is_active' => true]);
        $this->actingAs($buyer)->postJson(route('buyer.wishlist.toggle', $product))->assertOk()->assertExactJson(['saved' => true, 'count' => 1]);
        $this->actingAs($other)->postJson(route('buyer.wishlist.toggle', $product))->assertOk()->assertExactJson(['saved' => true, 'count' => 1]);
        $this->actingAs($buyer)->postJson(route('buyer.wishlist.toggle', $product))->assertOk()->assertExactJson(['saved' => false, 'count' => 0]);
        $this->assertDatabaseHas('wishlists', ['user_id' => $other->id, 'product_id' => $product->id]);
        $this->from(route('products.index'))->post(route('buyer.wishlist.toggle', $product))->assertRedirect(route('products.index'))->assertSessionHas('status', 'Saved to wishlist.');
    }
}
