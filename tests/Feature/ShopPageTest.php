<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SellerOrder;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every seller has a public shop page with followers, products (searchable, by category, sorted), rating and reviews.
 */
class ShopPageTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private int $shopId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Mara Cruz']);
        $this->seller->sellerShop()->update(['name' => 'Linen House', 'description' => 'Handmade linen from Ilocos.']);
        $this->shopId = Shop::idForSeller($this->seller);
    }

    public function test_anyone_can_open_a_shop_page_with_only_that_shops_visible_products(): void
    {
        $this->product('Linen Sheet', 1200, ['home_living']);
        $archived = $this->product('Old Pillowcase', 300, ['home_living']);
        $archived->update(['archived_at' => now(), 'is_active' => false]);
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Product::create(['seller_id' => $other->id, 'name' => 'Someone Else Mug', 'category' => 'home_living', 'price' => 99, 'stock' => 3, 'is_active' => true]);

        $this->get(route('shops.show', $this->shopId))->assertOk()
            ->assertSee('Linen House')->assertSee('Handmade linen from Ilocos.')
            ->assertSee('Linen Sheet')->assertDontSee('Old Pillowcase')->assertDontSee('Someone Else Mug')
            ->assertSee('Sign in to follow')->assertSee('data-follower-count', false)->assertSee('No ratings yet');

        $this->get('/shops/999999')->assertNotFound();
        $this->get('/shops/linen-house')->assertNotFound();
    }

    public function test_shops_of_sellers_who_are_not_active_are_hidden(): void
    {
        $this->seller->update(['status' => 'suspended']);

        $this->get(route('shops.show', $this->shopId))->assertNotFound();
    }

    public function test_buyers_follow_and_unfollow_and_see_the_shops_they_follow(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer);

        $this->postJson(route('buyer.shops.follow', $this->shopId))->assertOk()->assertExactJson(['following' => true, 'followers' => 1]);
        $this->get(route('shops.show', $this->shopId))->assertSee('aria-pressed="true"', false)->assertSee('Following');
        $this->get(route('buyer.following'))->assertOk()->assertSee('Linen House')->assertSee('1 follower');
        $this->get(route('buyer.account'))->assertSee(route('buyer.following'));

        // Without JavaScript the form returns to the page.
        $this->from(route('shops.show', $this->shopId))->post(route('buyer.shops.follow', $this->shopId))
            ->assertRedirect(route('shops.show', $this->shopId))->assertSessionHas('status', 'You unfollowed Linen House.');
        $this->assertDatabaseCount('shop_follows', 0);
        $this->get(route('buyer.following'))->assertSee('not following any shops yet');

        // Only buyers follow shops.
        $this->actingAs(User::factory()->create(['role' => 'seller', 'status' => 'active']))
            ->post(route('buyer.shops.follow', $this->shopId))->assertForbidden();
    }

    public function test_shoppers_search_filter_and_sort_within_a_shop(): void
    {
        $this->product('Linen Sheet', 1200, ['home_living']);
        $this->product('Linen Shirt', 900, ['clothing']);
        $this->product('Cotton Tote', 400, ['bags', 'home_living']);
        $url = route('shops.show', $this->shopId);

        $this->get($url.'?q=linen')->assertSee('Linen Sheet')->assertSee('Linen Shirt')->assertDontSee('Cotton Tote');
        $this->get($url.'?category=home_living')->assertSee('Linen Sheet')->assertSee('Cotton Tote')->assertDontSee('Linen Shirt');
        $this->get($url.'?sort=price_low')->assertSeeInOrder(['Cotton Tote', 'Linen Shirt', 'Linen Sheet']);
        $this->get($url.'?sort=price_high')->assertSeeInOrder(['Linen Sheet', 'Linen Shirt', 'Cotton Tote']);
        $this->get($url.'?sort=popular')->assertOk();
        $this->get($url)->assertSee('<option value="bags"', false);
        $this->get($url.'?q=nothing-like-this')->assertSee('No products match');
        $this->get($url.'?sort=random')->assertSessionHasErrors('sort');
    }

    public function test_rating_reviews_and_items_sold_come_from_delivered_orders(): void
    {
        $sheet = $this->product('Linen Sheet', 1200, ['home_living']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Ana Reyes']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'ZAY-SHOP', 'total_minor' => 240000, 'payment_method' => 'cod', 'shipping_address' => ['city' => 'Cebu']]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $this->shopId, 'subtotal_minor' => 240000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'delivered']);
        $itemId = DB::table('order_items')->insertGetId([
            'seller_order_id' => $sellerOrder->id, 'product_id' => $sheet->id, 'product_variant_id' => $sheet->variants()->value('id'),
            'product_name' => 'Linen Sheet', 'variant_name' => 'Default', 'unit_price_minor' => 120000, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);
        ProductReview::create(['order_item_id' => $itemId, 'product_id' => $sheet->id, 'user_id' => $buyer->id, 'rating' => 4, 'comment' => 'Soft and well made.']);

        $this->get(route('shops.show', $this->shopId))->assertOk()
            ->assertSee('4.0')->assertSee('Rating · 1 review')
            ->assertSee('Soft and well made.')->assertSee('Ana')->assertDontSee('Ana Reyes')
            ->assertSeeInOrder(['Items sold', '2']);
    }

    public function test_buyers_chat_from_the_shop_and_products_link_to_their_shop(): void
    {
        $sheet = $this->product('Linen Sheet', 1200, ['home_living']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $this->actingAs($buyer)->get(route('products.show', $sheet))->assertOk()
            ->assertSee('Sold by <a href="'.route('shops.show', $this->shopId).'"', false)->assertSee('Visit shop');
        $this->post(route('buyer.chat.shop', $this->shopId))->assertRedirect();
        $this->assertDatabaseHas('conversations', ['buyer_id' => $buyer->id, 'seller_id' => $this->seller->id, 'product_id' => null]);
        $this->post(route('buyer.chat.shop', $this->shopId));
        $this->assertSame(1, Conversation::count());
    }

    public function test_sellers_see_their_followers_and_a_link_to_their_shop_page(): void
    {
        foreach (range(1, 3) as $i) {
            \App\Models\ShopFollow::create(['user_id' => User::factory()->create(['role' => 'buyer'])->id, 'shop_id' => $this->shopId]);
        }

        $this->actingAs($this->seller)->get(route('seller.dashboard'))->assertOk()
            ->assertSee('View my shop page')->assertSee(route('shops.show', $this->shopId))->assertSeeInOrder(['Followers', '3']);
        $this->get(route('shops.show', $this->shopId))->assertOk()->assertSee('This is how buyers see your shop.')->assertDontSee('data-follow-form', false);
    }

    private function product(string $name, int $price, array $categories): Product
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => $name, 'category' => $categories[0], 'price' => $price, 'stock' => 5, 'is_active' => true]);
        $product->syncAdditionalCategories(array_slice($categories, 1));

        return $product;
    }
}
