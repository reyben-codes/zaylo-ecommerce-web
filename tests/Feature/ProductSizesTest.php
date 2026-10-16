<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductSizesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_color_gets_its_own_sizes_and_buyers_pick_color_then_size(): void
    {
        [$seller, $shoe, $black, $white] = $this->sneaker();

        $this->actingAs($seller)->get(route('seller.products.edit', $shoe))->assertOk()
            ->assertSee('Colors &amp; sizes', false)
            ->assertSee('<datalist id="size-presets">', false)->assertSee('value="US 9"', false);

        $this->patch(route('seller.products.stock', $shoe), [
            'sizes' => [$black->id => 'US 8', $white->id => 'US 8'],
            'variants' => [$black->id => 3, $white->id => 0],
            'new' => [
                ['color' => 'Black', 'size' => 'US 9', 'price' => 4500, 'stock' => 2],
                ['color' => 'White', 'size' => 'US 10', 'price' => 4500, 'stock' => 5],
                ['color' => 'White', 'size' => '', 'price' => 4500, 'stock' => 0],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(['Black / US 8', 'White / US 8', 'Black / US 9', 'White / US 10'], $shoe->variants()->orderBy('id')->pluck('name')->all());
        $blackNine = $shoe->variants()->where('name', 'Black / US 9')->firstOrFail();
        $this->assertSame(['color' => 'Black', 'size' => 'US 9'], $blackNine->options);
        $this->assertSame(4500.0, $blackNine->regular_price);

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('products.show', $shoe))->assertOk()
            ->assertSee('data-sized-picker', false)
            ->assertSee('Size<span class="variant-picker__for"> for Black</span>', false)
            ->assertSee('Size<span class="variant-picker__for"> for White</span>', false)
            ->assertSee('data-name="US 10"', false)
            // White's photo still links to the color, even though its options are now sizes.
            ->assertSee('data-image="/images/white.jpg"', false);

        $this->post(route('buyer.cart.add', $shoe), ['quantity' => 1])->assertSessionHasErrors('product_variant_id');
        $this->post(route('buyer.cart.add', $shoe), ['quantity' => 1, 'product_variant_id' => $white->id])->assertSessionHasErrors();
        $this->post(route('buyer.cart.add', $shoe), ['quantity' => 2, 'product_variant_id' => $blackNine->id])->assertSessionHasNoErrors();
        $this->get(route('buyer.cart'))->assertOk()->assertSee('Black / US 9');
    }

    public function test_bad_size_combinations_save_nothing(): void
    {
        [$seller, $shoe, $black] = $this->sneaker();
        $this->actingAs($seller);
        $add = fn (array $payload) => $this->from(route('seller.products.edit', $shoe))->patch(route('seller.products.stock', $shoe), $payload);

        // Black would have two options without telling them apart.
        $add(['new' => [['color' => 'Black', 'size' => 'US 9', 'price' => 4500, 'stock' => 1]]])
            ->assertSessionHasErrors(['variants' => 'Give every option of Black a size, or keep just one.']);
        $add(['sizes' => [$black->id => 'US 9'], 'new' => [['color' => 'Black', 'size' => 'us 9', 'price' => 4500, 'stock' => 1]]])
            ->assertSessionHasErrors(['variants' => 'Each size can only be listed once for Black.']);
        $add(['sizes' => [$black->id => 'US 9'], 'new' => [['color' => 'Purple', 'size' => 'US 8', 'price' => 4500, 'stock' => 1]]])
            ->assertSessionHasErrors('variants');
        $add(['new' => [['color' => 'Black', 'size' => 'US 9', 'stock' => 1]]])->assertSessionHasErrors('new.0.price');

        $this->assertSame(2, $shoe->variants()->count());
        $this->assertNull($black->fresh()->options['size'] ?? null);
    }

    public function test_seller_removes_a_size_and_buyers_carts_drop_it(): void
    {
        [$seller, $shoe, $black, $white] = $this->sneaker();
        $this->actingAs($seller)->patch(route('seller.products.stock', $shoe), [
            'sizes' => [$black->id => 'US 8'],
            'new' => [['color' => 'Black', 'size' => 'US 9', 'price' => 4500, 'stock' => 4]],
        ])->assertSessionHasNoErrors();
        $nine = $shoe->variants()->where('name', 'Black / US 9')->firstOrFail();

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->post(route('buyer.cart.add', $shoe), ['quantity' => 1, 'product_variant_id' => $nine->id])->assertSessionHasNoErrors();

        $this->actingAs($seller)->patch(route('seller.products.stock', $shoe), ['remove' => [$nine->id]])->assertSessionHasNoErrors();
        $this->assertNull(ProductVariant::find($nine->id));
        $this->assertSame(0, CartItem::where('product_variant_id', $nine->id)->count());

        $this->patch(route('seller.products.stock', $shoe), ['remove' => [$black->id, $white->id]])
            ->assertSessionHasErrors('variants');
        $this->assertSame(2, $shoe->variants()->count());
    }

    public function test_a_product_without_options_can_be_given_sizes(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $shirt = Product::create(['seller_id' => $seller->id, 'name' => 'Linen Shirt', 'category' => 'clothing', 'price' => 899, 'stock' => 7, 'is_active' => true]);

        $this->actingAs($seller)->get(route('seller.products.edit', $shirt))->assertOk()
            ->assertSee('Label this product with colors', false)->assertSee('value="XL"', false);
        $this->patch(route('seller.products.stock', $shirt), ['new' => [
            ['color' => '', 'size' => 'S', 'price' => 899, 'stock' => 3],
            ['color' => '', 'size' => 'M', 'price' => 899, 'stock' => 4],
        ]])->assertSessionHasNoErrors();

        // The product's single default option became the first size rather than staying beside it.
        $this->assertSame(['S', 'M'], $shirt->variants()->orderBy('id')->pluck('name')->all());
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))
            ->get(route('products.show', $shirt))->assertOk()->assertSee('Size:', false)->assertDontSee('data-sized-picker', false);
    }

    /** A two-color sneaker (Black in stock, White sold out), each color with its own photo. */
    private function sneaker(): array
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $make = function (string $name, int $stock, string $photo) use ($seller) {
            $product = Product::create(['seller_id' => $seller->id, 'name' => $name, 'category' => 'shoes', 'price' => 4500, 'stock' => $stock, 'is_active' => true]);
            ProductImage::create(['product_id' => $product->id, 'path' => $photo, 'sort_order' => 0]);

            return $product;
        };
        $shoe = $make('Court Sneaker Black', 5, '/images/black.jpg');
        $other = $make('Court Sneaker White', 0, '/images/white.jpg');
        Artisan::call('products:combine-colors', ['--product' => [$shoe->id, $other->id], '--color' => ['Black', 'White'], '--name' => 'Court Sneaker']);
        [$black, $white] = $shoe->variants()->orderBy('id')->get()->all();

        return [$seller, $shoe->fresh(), $black, $white];
    }
}
