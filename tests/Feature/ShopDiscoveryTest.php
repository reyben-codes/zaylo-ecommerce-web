<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SellerOrder;
use App\Models\ShopFollow;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Finding shops, the shop page's tabs and cover photo, and the app's shop screens. */
class ShopDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private int $shopId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'email_verified_at' => now()]);
        $this->seller->sellerShop()->update(['name' => 'Linen House']);
        $this->shopId = Shop::idForSeller($this->seller);
    }

    public function test_open_shops_can_be_searched_by_name(): void
    {
        $closed = User::factory()->create(['role' => 'seller', 'status' => 'suspended']);
        $closed->sellerShop()->update(['name' => 'Linen Closed', 'status' => 'approved']);

        $this->assertSame(['Linen House'], Shop::search(' LINEN ')->pluck('name')->all());
        $this->assertSame(['Linen House'], Shop::search('house')->pluck('name')->all());
        $this->assertCount(0, Shop::search('100%'));
        $this->assertCount(0, Shop::search('   '));

        $this->getJson(route('products.index', ['search' => 'linen', 'suggest' => 1]))->assertOk()
            ->assertJsonPath('shops.0.name', 'Linen House')->assertJsonPath('shops.0.url', route('shops.show', $this->shopId));
        $this->get(route('products.index', ['search' => 'linen']))->assertOk()
            ->assertSee('Matching shops')->assertSee(route('shops.show', $this->shopId));
        $this->get(route('products.index'))->assertOk()->assertDontSee('Matching shops');
        $this->getJson('/api/mobile/shops?search=linen')->assertOk()->assertJsonCount(1, 'shops')->assertJsonPath('shops.0.id', $this->shopId);
    }

    public function test_reviews_tab_ranks_products_by_rating_and_sales_both_ways(): void
    {
        $best = $this->product('Best Rated');
        $worst = $this->product('Worst Rated');
        $this->product('Never Reviewed');
        $this->sell($best, 5, 1);
        $this->sell($worst, 1, 9);
        $url = route('shops.show', $this->shopId);

        $this->get($url.'?tab=reviews&rank=highest_rated')->assertOk()->assertSeeInOrder(['Best Rated', 'Worst Rated', 'Never Reviewed']);
        $this->get($url.'?tab=reviews&rank=lowest_rated')->assertOk()->assertSeeInOrder(['Worst Rated', 'Best Rated', 'Never Reviewed']);
        $this->get($url.'?tab=reviews&rank=best_selling')->assertOk()->assertSeeInOrder(['Worst Rated', 'Best Rated', 'Never Reviewed']);
        $this->get($url.'?tab=reviews&rank=least_selling')->assertOk()->assertSeeInOrder(['Never Reviewed', 'Best Rated', 'Worst Rated']);
        $this->get($url.'?tab=reviews&rank=random')->assertSessionHasErrors('rank');
    }

    public function test_categories_tab_lists_the_shops_categories_and_links_to_their_products(): void
    {
        $this->product('Sheet', ['storage_bins']);
        $this->product('Tote', ['storage_bins']);
        $this->product('Zebra Duvet', ['bedsheets']);
        $url = route('shops.show', $this->shopId);

        $this->get($url.'?tab=categories')->assertOk()
            ->assertSee('Storage Bins')->assertSee('2 products')->assertSee('1 product')->assertDontSee('Zebra Duvet')
            ->assertSee('category=storage_bins', false);
        $this->get($url.'?category=bedsheets')->assertSee('Zebra Duvet')->assertDontSee('Tote');
    }

    public function test_only_the_seller_changes_their_cover_photo_and_buyers_see_it(): void
    {
        Storage::fake('public');
        $this->actingAs($this->seller);

        $this->post(route('seller.account.cover.update'), ['cover' => UploadedFile::fake()->image('cover.jpg', 2000, 2000)])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Cover photo updated.');
        $path = $this->seller->fresh()->getRawOriginal('shop_cover');
        Storage::disk('public')->assertExists($path);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1600, 600], [$width, $height]);
        $this->get(route('shops.show', $this->shopId))->assertSee(Storage::disk('public')->url($path));

        $this->post(route('seller.account.cover.update'), ['cover' => UploadedFile::fake()->image('tiny.jpg', 100, 100)])->assertSessionHasErrors('cover');
        $this->delete(route('seller.account.cover.destroy'))->assertSessionHas('status', 'Cover photo removed.');
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->seller->fresh()->shop_cover_url);

        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))
            ->post(route('seller.account.cover.update'), ['cover' => UploadedFile::fake()->image('cover.jpg', 800, 400)])->assertForbidden();
    }

    public function test_the_wishlist_links_to_the_shops_followed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))
            ->get(route('buyer.wishlist'))->assertOk()->assertSee('Shops Followed')->assertSee(route('buyer.following'));
    }

    public function test_the_app_shows_a_shop_its_products_and_links_products_to_it(): void
    {
        $sheet = $this->product('Linen Sheet', ['storage_bins']);
        $this->product('Cotton Tote', ['bedsheets']);
        $this->sell($sheet, 5, 3);

        $this->getJson("/api/mobile/shops/{$this->shopId}")->assertOk()
            ->assertJsonPath('shop.name', 'Linen House')->assertJsonPath('shop.product_count', 2)->assertJsonPath('shop.following', false)
            ->assertJsonPath('shop.sold', 3)->assertJsonPath('shop.cover_url', null)->assertJsonCount(2, 'shop.categories');
        $this->getJson("/api/mobile/shops/{$this->shopId}/products?search=linen")->assertOk()
            ->assertJsonCount(1, 'products.data')->assertJsonPath('products.data.0.units_sold', 3)->assertJsonPath('products.data.0.rating', 5);
        $this->getJson("/api/mobile/shops/{$this->shopId}/products?sort=least_selling")->assertOk()->assertJsonPath('products.data.0.name', 'Cotton Tote');
        $this->getJson("/api/mobile/shops/{$this->shopId}/products?category=bedsheets")->assertOk()->assertJsonPath('products.data.0.name', 'Cotton Tote');
        $this->getJson("/api/mobile/products/{$sheet->slug}")->assertOk()->assertJsonPath('product.shop_id', $this->shopId);
        $this->getJson('/api/mobile/shops/999999')->assertNotFound();
    }

    public function test_buyers_follow_list_and_chat_with_shops_in_the_app(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);

        $this->postJson("/api/mobile/shops/{$this->shopId}/follow")->assertUnauthorized();
        $this->withToken($buyer->createToken('test'));
        $this->getJson('/api/mobile/following')->assertOk()->assertJsonCount(0, 'shops');
        $this->postJson("/api/mobile/shops/{$this->shopId}/follow")->assertOk()->assertExactJson(['following' => true, 'followers' => 1]);
        $this->getJson('/api/mobile/following')->assertOk()->assertJsonPath('shops.0.name', 'Linen House')->assertJsonPath('shops.0.following', true);
        $this->getJson("/api/mobile/shops/{$this->shopId}")->assertOk()->assertJsonPath('shop.following', true)->assertJsonPath('shop.followers', 1);
        $this->assertSame(1, ShopFollow::where('shop_id', $this->shopId)->count());
        $this->postJson("/api/mobile/shops/{$this->shopId}/follow")->assertOk()->assertExactJson(['following' => false, 'followers' => 0]);

        $this->postJson("/api/mobile/shops/{$this->shopId}/chat")->assertOk()->assertJsonPath('conversation.name', 'Linen House');
        $this->assertDatabaseHas('conversations', ['buyer_id' => $buyer->id, 'seller_id' => $this->seller->id]);
    }

    private function product(string $name, array $categories = ['storage_bins']): Product
    {
        $product = Product::create(['seller_id' => $this->shopId, 'name' => $name, 'category' => $categories[0], 'price' => 100, 'stock' => 5, 'is_active' => true]);
        foreach (array_slice($categories, 1) as $category) {
            $product->additionalCategories()->create(['category' => $category]);
        }

        return $product;
    }

    /** A delivered sale of the product with a review. */
    private function sell(Product $product, int $rating, int $quantity): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'ZAY-'.$product->id, 'total_minor' => 10000, 'payment_method' => 'cod', 'shipping_address' => ['city' => 'Cebu']]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $this->shopId, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'delivered']);
        $itemId = DB::table('order_items')->insertGetId([
            'seller_order_id' => $sellerOrder->id, 'product_id' => $product->id, 'product_variant_id' => $product->variants()->value('id'),
            'product_name' => $product->name, 'variant_name' => 'Default', 'unit_price_minor' => 10000, 'quantity' => $quantity, 'created_at' => now(), 'updated_at' => now(),
        ]);
        ProductReview::create(['order_item_id' => $itemId, 'product_id' => $product->id, 'user_id' => $buyer->id, 'rating' => $rating]);
    }
}
