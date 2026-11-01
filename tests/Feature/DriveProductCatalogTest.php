<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\DriveProductSeeder;
use Database\Seeders\GardeningProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function seedSeller(): void
    {
        $user = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Seller::create([
            'user_id' => $user->id,
            'name' => 'Zaylo Store',
            'slug' => 'zaylo-store',
            'status' => 'approved',
        ]);
    }

    public function test_drive_catalog_imports_each_product_once_with_its_photos(): void
    {
        $this->seedSeller();
        $this->seed(GardeningProductSeeder::class);
        $this->seed(DriveProductSeeder::class);

        $this->assertSame(66, Product::count());
        $this->assertSame(197, ProductImage::count());
        $this->assertSame(197, ProductImage::distinct()->count('path'));
        $this->assertSame(2, Product::where('name', 'Green Electric Kettle')->firstOrFail()->images()->count());
        $this->assertSame(2, Product::where('name', 'Evoloop 1.7L Electric Kettle')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Professional Quiet Blender')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Commercial Digital Blender')->firstOrFail()->images()->count());
        $this->assertSame(2, Product::where('name', 'Black Round LED Decorative Mirror')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Gold Round LED Decorative Mirror')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Touchscreen Air Fryer')->firstOrFail()->images()->count());
        $this->assertSame(1, Product::where('name', 'Compact Black Air Fryer')->firstOrFail()->images()->count());
        $this->assertSame(['air_fryers'], Product::where('name', 'Touchscreen Air Fryer')->firstOrFail()->category_keys);
        $this->assertSame(['sanding_tools'], Product::where('name', 'Neilsen CT3997 Power Sander')->firstOrFail()->category_keys);

        $this->seed(DriveProductSeeder::class);
        $this->assertSame(66, Product::count());
        $this->assertSame(197, ProductImage::count());
    }

    public function test_multiple_product_photos_are_shown_as_carousels(): void
    {
        $this->seedSeller();
        $this->seed(DriveProductSeeder::class);
        $comforter = Product::where('name', 'Kathy Ireland 6-Piece Comforter Set')->firstOrFail();

        $this->get(route('products.index', ['search' => 'Kathy Ireland 6-Piece Comforter Set']))
            ->assertOk()
            ->assertSee('data-card-slides', false)
            ->assertSee('1 / 6');

        $this->get(route('products.show', $comforter))
            ->assertOk()
            ->assertSee('data-gallery-zoom', false)
            ->assertSee('data-gallery-prev', false)
            ->assertSee('data-gallery-next', false)
            ->assertSee('View image 6 of 6');
    }
}
