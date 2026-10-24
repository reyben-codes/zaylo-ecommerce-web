<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductPhotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_adds_several_photos_and_buyers_get_a_carousel(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload() + [
            'image' => UploadedFile::fake()->image('main.jpg'),
            'photos' => [UploadedFile::fake()->image('side.jpg'), UploadedFile::fake()->image('back.png')],
        ])->assertSessionHasNoErrors();
        $product = Product::where('name', 'Canvas Backpack')->firstOrFail();

        $this->assertCount(2, $product->extra_photos);
        $product->extra_photos->each(fn ($photo) => Storage::disk('public')->assertExists(Str::after($photo->path, '/storage/')));
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('name="remove_photos[]"', false);

        $product->forceFill(['review_status' => Product::REVIEW_APPROVED])->save(); // New listings wait for an admin.
        auth()->logout();
        $this->get(route('products.show', $product))->assertOk()->assertSee('View image 3 of 3');
        $this->get(route('products.index'))->assertOk()->assertSee('1 / 3');
    }

    public function test_seller_removes_and_adds_photos_within_the_limit(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload() + [
            'image' => UploadedFile::fake()->image('main.jpg'),
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg"), range(1, 6)),
        ])->assertSessionHasNoErrors();
        $product = Product::where('name', 'Canvas Backpack')->firstOrFail();
        $removed = $product->extra_photos->first();

        // Six kept plus four new is over the nine extra photos allowed, so nothing changes.
        $this->put(route('seller.products.update', $product), $this->payload() + [
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("more-{$i}.jpg"), range(1, 4)),
        ])->assertSessionHasErrors('photos');
        $this->assertCount(6, $product->fresh()->extra_photos);

        // Removing one makes room; the removed photo's file is deleted too.
        $this->put(route('seller.products.update', $product), $this->payload() + [
            'remove_photos' => [$removed->id],
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("more-{$i}.jpg"), range(1, 4)),
        ])->assertSessionHasNoErrors();
        $this->assertCount(9, $product->fresh()->extra_photos);
        $this->assertDatabaseMissing('product_images', ['id' => $removed->id]);
        Storage::disk('public')->assertMissing(Str::after($removed->path, '/storage/'));

        $this->post(route('seller.products.store'), [
            'name' => 'Too Many Photos',
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg"), range(1, 7)),
        ] + $this->payload())->assertSessionHasErrors('photos');
        $this->assertDatabaseMissing('products', ['name' => 'Too Many Photos']);
    }

    public function test_sellers_cannot_remove_another_sellers_photos(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($owner)->post(route('seller.products.store'), $this->payload() + [
            'image' => UploadedFile::fake()->image('main.jpg'),
            'photos' => [UploadedFile::fake()->image('side.jpg')],
        ]);
        $theirs = Product::where('name', 'Canvas Backpack')->firstOrFail();
        $photoId = $theirs->extra_photos->first()->id;

        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($other)->post(route('seller.products.store'), ['name' => 'Other Bag'] + $this->payload());
        $mine = Product::where('name', 'Other Bag')->firstOrFail();
        $this->put(route('seller.products.update', $mine), ['name' => 'Other Bag', 'remove_photos' => [$photoId]] + $this->payload());

        $this->assertDatabaseHas('product_images', ['id' => $photoId]);
    }

    public function test_the_first_photo_is_the_main_photo_when_none_is_chosen(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload() + [
            'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')],
        ])->assertSessionHasNoErrors();
        $product = Product::where('name', 'Canvas Backpack')->firstOrFail();

        $this->assertSame($product->images->first()->path, $product->image_url);
        $this->assertCount(1, $product->extra_photos);
    }

    private function payload(): array
    {
        return [
            'name' => 'Canvas Backpack', 'category' => 'bags', 'description' => 'A roomy everyday backpack.',
            'price' => 1299, 'stock' => 8, 'low_stock_threshold' => 2, 'is_active' => 1,
        ];
    }
}
