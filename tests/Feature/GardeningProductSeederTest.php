<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\GardeningProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GardeningProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_local_gardening_products_with_galleries_and_variants(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->seed(GardeningProductSeeder::class);

        $wateringCan = Product::where('name', '1.2L Long Spout Watering Can')->firstOrFail();
        $gloves = Product::where('name', 'COOLJOB Gardening Gloves - 6 Pairs')->firstOrFail();

        $this->assertSame($seller->id, $wateringCan->seller_id);
        $this->assertStringStartsWith('/images/', $wateringCan->image_url);
        $this->assertCount(3, $wateringCan->images);
        $this->assertCount(3, $wateringCan->variants);
        $this->assertCount(4, $gloves->images);
        $this->assertCount(3, $gloves->variants);
        $this->assertDatabaseHas('products', ['name' => 'Bully Tools 14-Gauge Round Point Shovel']);
        $this->assertDatabaseHas('products', ['name' => 'Westward 6-Inch Steel Blade Garden Hoe']);
    }

    public function test_product_page_renders_the_seeded_image_gallery(): void
    {
        User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->seed(GardeningProductSeeder::class);
        $product = Product::where('name', 'Bully Tools 14-Gauge Round Point Shovel')->firstOrFail();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('data-product-gallery', false)
            ->assertSee('Close-up of the 14-gauge shovel blade')
            ->assertSee('/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle3.webp');
    }

    public function test_a_variant_must_be_selected_before_adding_a_variant_product_to_the_cart(): void
    {
        User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->seed(GardeningProductSeeder::class);
        $product = Product::where('name', '1.2L Long Spout Watering Can')->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.cart.add', $product), ['quantity' => 1])
            ->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }
}
