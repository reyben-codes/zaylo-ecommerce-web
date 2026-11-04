<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Like the app, the product page says how many of the chosen size are available, not just the total.
 */
class VariantStockLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_page_shows_the_stock_of_the_preselected_size(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Linen Shirt', 'category' => 'clothing', 'price' => 900, 'stock' => 0, 'is_active' => true]);
        $product->variants()->delete();
        $product->variants()->create(['sku' => 'LIN-S', 'name' => 'S', 'options' => ['size' => 'S'], 'price_minor' => 90000, 'stock' => 0, 'is_active' => true]);
        $product->variants()->create(['sku' => 'LIN-M', 'name' => 'M', 'options' => ['size' => 'M'], 'price_minor' => 90000, 'stock' => 7, 'is_active' => true]);
        $product->variants()->create(['sku' => 'LIN-L', 'name' => 'L', 'options' => ['size' => 'L'], 'price_minor' => 90000, 'stock' => 3, 'is_active' => true]);

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('10 available')
            ->assertSee('<p class="variant-stock" data-variant-stock aria-live="polite">7 available</p>', false)
            ->assertSee('data-stock="3"', false);
    }

    public function test_a_product_without_options_keeps_only_the_total(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Clay Pot', 'category' => 'storage_bins', 'price' => 300, 'stock' => 4, 'is_active' => true]);

        $this->get(route('products.show', $product))->assertOk()->assertSee('4 available')->assertDontSee('data-variant-stock', false);
    }
}
