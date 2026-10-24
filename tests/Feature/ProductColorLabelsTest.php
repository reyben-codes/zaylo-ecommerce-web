<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductColorLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_lists_a_new_product_in_colors_and_buyers_pick_one(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()
            ->assertSee('name="options[0][color]"', false)->assertSee('data-option-chip="Navy"', false);

        $this->post(route('seller.products.store'), [
            'name' => 'Canvas Tote', 'category' => 'bags', 'price' => 650, 'stock' => 8, 'low_stock_threshold' => 2, 'is_active' => 1,
            'colors' => ' Black, olive green,, black , Navy ',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tote = Product::where('name', 'Canvas Tote')->firstOrFail();
        $variants = $tote->variants()->orderBy('id')->get();
        $this->assertSame(['Black', 'olive green', 'Navy'], $variants->pluck('name')->all());
        $this->assertSame(['color' => 'Navy'], $variants->last()->options);
        $this->assertSame([8, 8, 8], $variants->pluck('stock')->all());
        $this->assertSame([650.0, 650.0, 650.0], $variants->map->regular_price->all());

        $this->get(route('seller.products.edit', $tote))->assertOk()
            ->assertSee('value="olive green"', false)->assertSee('Add a color');
        $tote->forceFill(['review_status' => Product::REVIEW_APPROVED])->save(); // New listings wait for an admin.
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))
            ->get(route('products.show', $tote))->assertOk()->assertSee('Color:', false)->assertSee('olive green');
    }

    public function test_long_or_too_many_colors_are_refused(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $payload = ['name' => 'Tee', 'category' => 'clothing', 'price' => 300, 'stock' => 1, 'low_stock_threshold' => 1];

        $this->actingAs($seller)->post(route('seller.products.store'), $payload + ['colors' => str_repeat('a', 41)])->assertSessionHasErrors('colors');
        $this->post(route('seller.products.store'), $payload + ['colors' => implode(',', range(1, 21))])->assertSessionHasErrors('colors');
        $this->assertSame(0, Product::count());
    }

    public function test_seller_names_a_simple_products_color_and_adds_another_with_sizes(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $shirt = Product::create(['seller_id' => $seller->id, 'name' => 'Linen Shirt', 'category' => 'clothing', 'price' => 899, 'stock' => 7, 'is_active' => true]);
        $this->actingAs($seller);

        // A new color beside an unnamed option would show buyers "Other", so the existing one must be named too.
        $this->patch(route('seller.products.stock', $shirt), ['added_color' => 'Sage', 'added' => [['size' => '', 'price' => 899, 'stock' => 2]]])
            ->assertSessionHasErrors('colors');
        $this->assertSame(1, $shirt->variants()->count());

        $this->patch(route('seller.products.stock', $shirt), [
            'colors' => [['from' => '', 'to' => 'White']],
            'added_color' => 'Sage',
            'added' => [['size' => 'S', 'price' => 950, 'stock' => 3], ['size' => 'M', 'price' => 950, 'stock' => 0], ['size' => '', 'price' => 950, 'stock' => 0]],
        ])->assertSessionHasNoErrors();

        $variants = $shirt->variants()->orderBy('id')->get();
        $this->assertSame(['White', 'Sage / S', 'Sage / M'], $variants->pluck('name')->all());
        $this->assertSame(7, $variants->first()->stock);
        $this->assertSame(950.0, $variants[1]->regular_price);

        // The same color can't be added twice, ignoring capitals.
        $this->patch(route('seller.products.stock', $shirt), ['added_color' => 'sage'])->assertSessionHasErrors('added_color');
    }

    public function test_renaming_a_color_keeps_its_photo_and_sizes(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $make = function (string $name, string $photo) use ($seller) {
            $product = Product::create(['seller_id' => $seller->id, 'name' => $name, 'category' => 'shoes', 'price' => 4500, 'stock' => 4, 'is_active' => true]);
            ProductImage::create(['product_id' => $product->id, 'path' => $photo, 'sort_order' => 0]);

            return $product;
        };
        $shoe = $make('Runner Black', '/images/black.jpg');
        $other = $make('Runner White', '/images/white.jpg');
        Artisan::call('products:combine-colors', ['--product' => [$shoe->id, $other->id], '--color' => ['Black', 'White'], '--name' => 'Runner']);

        $this->actingAs($seller)->patch(route('seller.products.stock', $shoe), [
            'colors' => [['from' => 'Black', 'to' => 'Black'], ['from' => 'White', 'to' => 'Chalk White']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Black', 'Chalk White'], $shoe->variants()->orderBy('id')->pluck('name')->all());
        $this->assertSame('Runner — Chalk White', ProductImage::where('path', '/images/white.jpg')->value('alt_text'));
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))
            ->get(route('products.show', $shoe))->assertOk()->assertSee('data-image="/images/white.jpg"', false);

        // Two colors that differ only by capitals are refused rather than shown side by side.
        $this->actingAs($seller)->patch(route('seller.products.stock', $shoe), [
            'colors' => [['from' => 'Chalk White', 'to' => 'black']],
        ])->assertSessionHasErrors('colors');
    }
}
