<?php

namespace Tests\Feature;

use App\Models\{Order, OrderItem, Product, ProductReview, SellerOrder, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII='));
    }

    private function purchase(string $status = 'delivered'): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Review shoe', 'category' => 'shoes', 'price' => 100, 'stock' => 5, 'is_active' => true]);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'TEST-'.$buyer->id, 'total_minor' => 10000, 'payment_method' => 'cod', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $product->getRawOriginal('seller_id'), 'subtotal_minor' => 10000, 'status' => $status]);
        $item = $sellerOrder->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id, 'product_name' => $product->name, 'variant_name' => 'Default', 'unit_price_minor' => 10000, 'quantity' => 1]);
        return [$buyer, $item, $product];
    }

    public function test_received_purchase_can_be_reviewed_with_photos_and_public_rating(): void
    {
        Storage::fake('public');
        [$buyer, $item, $product] = $this->purchase();
        $this->actingAs($buyer)->get(route('buyer.reviews.create', $item))->assertOk()->assertSee('Review your purchase');
        $this->post(route('buyer.reviews.store', $item), ['rating' => 5, 'comment' => 'Great <script>alert(1)</script>', 'photos' => [$this->photo()]])->assertRedirect(route('buyer.orders'));
        $review = ProductReview::firstOrFail();
        $this->assertSame(5, $review->rating);
        Storage::disk('public')->assertExists($review->photos[0]);
        $this->get(route('products.show', $product))->assertOk()->assertSee('5.0/5')->assertSee('Verified purchase')->assertDontSee('<script>alert(1)</script>', false);
        $this->post(route('buyer.reviews.store', $item), ['rating' => 1])->assertSessionHasErrors('review');
        $this->assertDatabaseCount('product_reviews', 1);
    }

    public function test_other_buyers_and_undelivered_orders_cannot_review(): void
    {
        [$buyer, $item] = $this->purchase('shipped');
        $this->actingAs($buyer)->post(route('buyer.reviews.store', $item), ['rating' => 5])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))
            ->post(route('buyer.reviews.store', $item), ['rating' => 5])->assertForbidden();
        $this->get(route('buyer.reviews.create', $item))->assertForbidden();
        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_review_form_shows_product_photo_and_purchase_details_with_missing_photo_fallback(): void
    {
        [$buyer, $item, $product] = $this->purchase();
        $this->actingAs($buyer)->get(route('buyer.reviews.create', $item))->assertOk()
            ->assertSee('Product photo unavailable')->assertSee('Quantity purchased: 1');
        $product->images()->create(['path' => '/images/review-test-shoe.jpg', 'alt_text' => 'Shoe', 'sort_order' => 0]);
        $this->get(route('buyer.reviews.create', $item))->assertOk()
            ->assertSee('src="/images/review-test-shoe.jpg"', false)
            ->assertSee('alt="Review shoe"', false)->assertSee('Default');
    }

    public function test_ratings_and_photo_limits_are_validated(): void
    {
        Storage::fake('public');
        [$buyer, $item] = $this->purchase();
        $this->actingAs($buyer);
        foreach ([0, 6, 'bad'] as $rating) $this->postJson(route('buyer.reviews.store', $item), ['rating' => $rating])->assertUnprocessable();
        $this->post(route('buyer.reviews.store', $item), ['rating' => 4, 'photos' => [UploadedFile::fake()->create('code.svg', 1, 'image/svg+xml')]])->assertSessionHasErrors('photos.0');
        $this->post(route('buyer.reviews.store', $item), ['rating' => 4, 'photos' => array_map(fn () => $this->photo(), range(1, 4))])->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('product_reviews', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_reviews_accumulate_in_buyer_product_card_summaries(): void
    {
        [$buyer, $item, $product] = $this->purchase();
        [$secondBuyer, $secondItem] = $this->purchase();
        $secondItem->update(['product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id]);
        $this->actingAs($buyer)->post(route('buyer.reviews.store', $item), ['rating' => 5])->assertRedirect();
        $this->get(route('products.index'))->assertOk()->assertSee('5.0 out of 5 stars, 1 review. Read customer reviews');
        $this->actingAs($secondBuyer)->post(route('buyer.reviews.store', $secondItem), ['rating' => 3])->assertRedirect();
        $summary = Product::withReviewSummary()->findOrFail($product->id);
        $this->assertSame(2, $summary->reviews_count);
        $this->assertSame(4.0, (float) $summary->reviews_avg_rating);
        foreach (['home', 'products.index', 'buyer.dashboard'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('4.0 out of 5 stars, 2 reviews. Read customer reviews')
                ->assertSee(route('products.show', $product).'#customer-reviews', false);
        }
        $this->get(route('products.show', $product))->assertOk()->assertSee('4.0/5')->assertSee('2 reviews');
    }
}
