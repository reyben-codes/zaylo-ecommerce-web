<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoldOutDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_sold_out_products_show_a_grayed_photo_and_label(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $soldOut = Product::create(['seller_id' => $seller->id, 'name' => 'Empty Shelf Lamp', 'category' => 'home_living', 'price' => 500, 'stock' => 0, 'is_active' => true, 'image_url' => '/images/lamp.jpg']);
        $inStock = Product::create(['seller_id' => $seller->id, 'name' => 'Full Shelf Lamp', 'category' => 'home_living', 'price' => 500, 'stock' => 4, 'is_active' => true, 'image_url' => '/images/full.jpg']);

        $this->assertTrue($soldOut->fresh()->is_sold_out);
        $this->assertFalse($inStock->fresh()->is_sold_out);

        $catalog = $this->get(route('products.index', ['availability' => 'all']))->assertOk()->getContent();
        $this->assertSame(1, substr_count($catalog, 'class="product-sold-out-label"'));
        $this->assertStringContainsString('alt="Empty Shelf Lamp (sold out)"', $catalog);
        $this->assertStringContainsString('class="is-sold-out"', $catalog);
        $this->assertStringContainsString('alt="Full Shelf Lamp"', $catalog);

        $this->get(route('products.show', $soldOut))->assertOk()->assertSee('detail-image is-sold-out', false)->assertSee('Sold out');
        $this->get(route('products.show', $inStock))->assertOk()->assertDontSee('detail-image is-sold-out', false);
    }
}
