<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_sees_their_real_catalog_under_their_shop_name(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'Happy Heels']);
        $owned = $this->product($seller, ['name' => 'Happy Heels Pump']);
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $notOwned = $this->product($otherSeller, ['name' => 'Another Shop Sneaker']);

        $this->actingAs($seller)->get(route('seller.products'))
            ->assertOk()
            ->assertSee('Happy Heels')
            ->assertSee($owned->name)
            ->assertDontSee($notOwned->name)
            ->assertSee(route('seller.products.update', $owned));
    }

    public function test_seller_can_update_catalog_details_and_upload_a_product_picture(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'Happy Heels']);
        $product = $this->product($seller);

        $this->actingAs($seller)->put(route('seller.products.update', $product), [
            'name' => 'Updated Leather Heel',
            'sku' => 'HH-HEEL-001',
            'category' => 'shoes',
            'gender' => 'women',
            'description' => 'A refreshed description shown to buyers.',
            'price' => 2199.50,
            'original_price' => 2599,
            'stock' => 18,
            'low_stock_threshold' => 4,
            'weight_grams' => 640,
            'badge' => 'New',
            'is_active' => 1,
            'image' => UploadedFile::fake()->createWithContent(
                'heel.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Updated Leather Heel', $product->name);
        $this->assertSame('2199.50', $product->price);
        $this->assertSame('A refreshed description shown to buyers.', $product->description);
        $this->assertSame(18, $product->stock);
        $this->assertStringStartsWith('/storage/products/'.$seller->id.'/', $product->image_url);
        Storage::disk('public')->assertExists(substr($product->image_url, strlen('/storage/')));

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Updated Leather Heel')
            ->assertSee('A refreshed description shown to buyers.')
            ->assertSee('Happy Heels');
    }

    private function product(User $seller, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $seller->id,
            'name' => 'Classic Heel',
            'category' => 'shoes',
            'gender' => 'women',
            'description' => 'Original product description.',
            'price' => 1899,
            'stock' => 10,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ], $overrides));
    }
}
