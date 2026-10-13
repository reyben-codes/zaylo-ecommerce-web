<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductColorOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_combining_color_listings_creates_one_product_with_a_color_picker_and_sold_out_colors(): void
    {
        [$seller, $black, $white] = $this->listings();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $this->assertSame(0, Artisan::call('products:combine-colors', [
            '--product' => [$black->id, $white->id], '--color' => ['Black', 'White'], '--name' => 'Elite Gourmet Rice Cooker',
        ]));

        $black->refresh();
        $this->assertSame('Elite Gourmet Rice Cooker', $black->name);
        $this->assertSame(['Black', 'White'], $black->variants()->orderBy('id')->pluck('name')->all());
        $this->assertFalse($white->fresh()->is_active);
        $this->assertSame('Elite Gourmet Rice Cooker — White', ProductImage::where('path', '/images/white.jpg')->value('alt_text'));
        $this->assertSame($black->id, ProductImage::where('path', '/images/white.jpg')->value('product_id'));

        $this->actingAs($buyer)->get(route('products.show', $black))->assertOk()
            ->assertSee('Color:', false)
            ->assertSee('data-name="Black"', false)
            ->assertSee('data-image="/images/white.jpg"', false)
            ->assertSee('Sold out')
            // The sold-out color's photo is grayed in the gallery; the in-stock photo shown first is not.
            ->assertSee('View image 2 of 2 (sold out)', false)
            ->assertSee('detail-thumbnail is-sold-out', false)
            ->assertDontSee('class="detail-image is-sold-out"', false);
        $this->get(route('products.show', $white))->assertNotFound();

        $soldOut = $black->variants()->where('name', 'White')->firstOrFail();
        $this->post(route('buyer.cart.add', $black), ['quantity' => 1, 'product_variant_id' => $soldOut->id])->assertSessionHasErrors();
        $inStock = $black->variants()->where('name', 'Black')->firstOrFail();
        $this->post(route('buyer.cart.add', $black), ['quantity' => 1, 'product_variant_id' => $inStock->id])->assertSessionHasNoErrors();
    }

    public function test_dry_run_changes_nothing_and_unsafe_combinations_are_refused(): void
    {
        [$seller, $black, $white] = $this->listings();

        $this->assertSame(0, Artisan::call('products:combine-colors', ['--product' => [$black->id, $white->id], '--color' => ['Black', 'White'], '--dry-run' => true]));
        $this->assertTrue($white->fresh()->is_active);
        $this->assertSame(1, $black->variants()->count());

        $other = Product::create(['seller_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Other', 'category' => 'home_living', 'price' => 10, 'stock' => 1, 'is_active' => true]);
        $this->assertSame(1, Artisan::call('products:combine-colors', ['--product' => [$black->id, $other->id], '--color' => ['Black', 'Red']]));
        $this->assertSame(2, Artisan::call('products:combine-colors', ['--product' => [$black->id, $white->id], '--color' => ['Black', 'black']]));
        $this->assertTrue($white->fresh()->is_active);
    }

    public function test_seller_sets_price_and_stock_per_color(): void
    {
        [$seller, $black, $white] = $this->listings();
        Artisan::call('products:combine-colors', ['--product' => [$black->id, $white->id], '--color' => ['Black', 'White']]);
        [$blackOption, $whiteOption] = $black->variants()->orderBy('id')->get()->all();

        $this->actingAs($seller)->get(route('seller.products.edit', $black))->assertOk()
            ->assertSee('Colors &amp; sizes', false)->assertSee('name="prices['.$whiteOption->id.']"', false)->assertSee('Sold out');
        $this->patch(route('seller.products.stock', $black), [
            'variants' => [$blackOption->id => 4, $whiteOption->id => 9],
            'prices' => [$blackOption->id => 1999, $whiteOption->id => 2099.50],
        ])->assertSessionHasNoErrors();

        $this->assertSame(9, $whiteOption->fresh()->stock);
        $this->assertSame(2099.5, $whiteOption->fresh()->regular_price);
        $this->get(route('seller.inventory'))->assertOk()->assertSee('White')->assertSee('13');
    }

    private function listings(): array
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $make = function (string $name, int $stock, string $photo) use ($seller) {
            $product = Product::create(['seller_id' => $seller->id, 'name' => $name, 'category' => 'home_living', 'price' => 1999, 'stock' => $stock, 'is_active' => true]);
            ProductImage::create(['product_id' => $product->id, 'path' => $photo, 'sort_order' => 0]);

            return $product;
        };

        return [$seller, $make('Elite Gourmet Black Rice Cooker', 18, '/images/black.jpg'), $make('Elite Gourmet White Rice Cooker', 0, '/images/white.jpg')];
    }
}
