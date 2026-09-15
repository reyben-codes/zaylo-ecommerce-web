<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_navigation_is_consistent_and_counts_follow_saved_changes(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create([
            'seller_id' => $seller->id, 'name' => 'Navigation test product',
            'category' => 'electronics', 'description' => 'Test product',
            'price' => 500, 'stock' => 20, 'is_active' => true,
        ]);
        // Another buyer's saved items must never contribute to these badges.
        $otherBuyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $otherBuyer->cart()->create()->items()->create(['product_id' => $product->id, 'quantity' => 9]);
        $otherBuyer->wishlistItems()->create(['product_id' => $product->id]);

        $this->actingAs($buyer);
        $this->assertCounts(route('buyer.dashboard'), 0, 0);
        $this->post(route('buyer.cart.add', $product), ['quantity' => 3])->assertRedirect(route('buyer.cart'));
        $this->post(route('buyer.wishlist.toggle', $product))->assertSessionHasNoErrors();

        $baseline = null;
        foreach (['buyer.dashboard', 'buyer.cart', 'buyer.wishlist', 'buyer.orders', 'buyer.account', 'products.index', 'home'] as $route) {
            $xpath = $this->assertCounts(route($route), 3, 1);
            $links = [];
            foreach ($xpath->query('//header//nav[@aria-label="Store navigation"]/a') as $link) {
                $links[] = [$link->textContent, $link->getAttribute('href')];
            }
            $this->assertNotEmpty($links);
            $this->assertSame(route('buyer.dashboard'), $links[0][1]);
            $baseline ??= $links;
            $this->assertSame($baseline, $links);
        }
        $this->assertCounts(route('products.show', $product), 3, 1);

        $item = $buyer->cart()->first()->items()->first();
        $this->patch(route('buyer.cart.update', $item), ['quantity' => 5])->assertSessionHasNoErrors();
        $this->assertCounts(route('buyer.cart'), 5, 1);
        $this->delete(route('buyer.cart.remove', $item))->assertSessionHasNoErrors();
        $this->post(route('buyer.wishlist.toggle', $product))->assertSessionHasNoErrors();
        $this->assertCounts(route('buyer.dashboard'), 0, 0);
        $this->assertCounts(route('buyer.wishlist'), 0, 0);
    }

    public function test_guest_pages_do_not_show_buyer_counts(): void
    {
        foreach (['home', 'products.index'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertDontSee('data-cart-count', false)
                ->assertDontSee('data-wishlist-count', false);
        }
    }

    private function assertCounts(string $url, int $cartQuantity, int $wishlistCount): DOMXPath
    {
        $response = $this->get($url)->assertOk();
        $document = new DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);

        foreach (['cart' => $cartQuantity, 'wishlist' => $wishlistCount] as $type => $count) {
            $badges = $xpath->query('//header//span[@data-'.$type.'-count]');
            $this->assertSame($count > 0 ? 1 : 0, $badges->length);
            if ($count > 0) {
                $this->assertSame((string) $count, trim($badges->item(0)->textContent));
            }
            $destination = route('buyer.'.$type);
            $this->assertSame(1, $xpath->query('//header//a[@href="'.$destination.'"]')->length);
        }

        $this->assertSame(1, $xpath->query('//header//a[@href="'.route('buyer.account').'"]')->length);

        return $xpath;
    }
}
