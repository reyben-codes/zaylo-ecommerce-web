<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\DriveProductSeeder;
use Database\Seeders\GardeningProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_drive_catalog_imports_each_product_once_with_its_photos(): void
    {
        User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->seed(GardeningProductSeeder::class);
        $this->seed(DriveProductSeeder::class);

        $this->assertSame(69, Product::count());
        $this->assertSame(206, ProductImage::count());
        $this->assertSame(206, ProductImage::distinct()->count('path'));
        $this->assertSame(3, Product::where('name', 'Nike Dunk Low Retro Panda')->firstOrFail()->images()->count());
        $this->assertSame(2, Product::where('name', 'Green Electric Kettle')->firstOrFail()->images()->count());
        $this->assertSame(2, Product::where('name', 'Evoloop 1.7L Electric Kettle')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Professional Quiet Blender')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Commercial Digital Blender')->firstOrFail()->images()->count());
        $this->assertSame(2, Product::where('name', 'Black Round LED Decorative Mirror')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Gold Round LED Decorative Mirror')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Touchscreen Air Fryer')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Compact Black Air Fryer')->firstOrFail()->images()->count());
        $this->assertTrue(Product::where('name', 'Nike Dunk Low Retro Panda')->firstOrFail()->is_active);

        $this->seed(DriveProductSeeder::class);
        $this->assertSame(69, Product::count());
        $this->assertSame(206, ProductImage::count());
    }

    public function test_multiple_product_photos_are_shown_as_carousels(): void
    {
        User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->seed(DriveProductSeeder::class);
        $shoe = Product::where('name', 'Nike Dunk Low Retro Panda')->firstOrFail();

        $this->get(route('products.index', ['search' => 'Nike Dunk Low Retro Panda']))
            ->assertOk()
            ->assertSee('data-card-slides', false)
            ->assertSee('1 / 3');

        $this->get(route('products.show', $shoe))
            ->assertOk()
            ->assertSee('data-gallery-prev', false)
            ->assertSee('data-gallery-next', false)
            ->assertSee('View image 3 of 3');
    }
}
