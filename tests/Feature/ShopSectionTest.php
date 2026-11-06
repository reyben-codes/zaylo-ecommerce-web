<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopSection;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shop sections: a seller's own groupings shown as chips on their shop page (website and app), managed under
 * Seller Center > Products > Shop sections and on each product's edit page. They never touch marketplace categories.
 */
class ShopSectionTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private int $shopId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->seller->sellerShop()->update(['name' => 'Linen House']);
        $this->shopId = Shop::idForSeller($this->seller);
    }

    public function test_seller_adds_renames_hides_reorders_and_deletes_sections(): void
    {
        $this->actingAs($this->seller)->get(route('seller.products.sections'))->assertOk()
            ->assertSee('Shop sections')->assertSee('No sections yet')->assertSee('0 of 20 used');
        $this->get(route('seller.products'))->assertOk()->assertSee(route('seller.products.sections'));

        $this->post(route('seller.products.sections.store'), ['name' => '  New   Arrivals '])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('seller.products.sections.store'), ['name' => 'Bundle Deals'])->assertSessionHasNoErrors();
        $this->post(route('seller.products.sections.store'), ['name' => 'new arrivals'])->assertSessionHasErrors('name');
        $this->post(route('seller.products.sections.store'), ['name' => 'X'])->assertSessionHasErrors('name');
        $this->post(route('seller.products.sections.store'), ['name' => str_repeat('a', 31)])->assertSessionHasErrors('name');

        [$arrivals, $bundles] = ShopSection::forShop($this->shopId)->get()->all();
        $this->assertSame('New Arrivals', $arrivals->name);
        $this->assertSame([1, 2], [$arrivals->position, $bundles->position]);

        $this->put(route('seller.products.sections.update', $bundles), ['name' => 'Bundles', 'section_id' => $bundles->id])->assertSessionHasNoErrors();
        $this->assertSame('Bundles', $bundles->fresh()->name);
        $this->assertFalse($bundles->fresh()->is_visible, 'An unticked "Show" box hides the section.');

        $this->patch(route('seller.products.sections.move', $bundles), ['direction' => 'up'])->assertRedirect();
        $this->assertSame(['Bundles', 'New Arrivals'], ShopSection::forShop($this->shopId)->pluck('name')->all());
        $this->patch(route('seller.products.sections.move', $bundles), ['direction' => 'up'])->assertRedirect();
        $this->assertSame(['Bundles', 'New Arrivals'], ShopSection::forShop($this->shopId)->pluck('name')->all());

        $product = $this->product('Linen Sheet');
        $bundles->products()->attach($product);
        $this->delete(route('seller.products.sections.destroy', $bundles))->assertRedirect();
        $this->assertModelMissing($bundles);
        $this->assertModelExists($product);
        $this->assertDatabaseMissing('product_shop_section', ['product_id' => $product->id]);
    }

    public function test_a_shop_can_have_at_most_twenty_sections(): void
    {
        foreach (range(1, ShopSection::MAX_PER_SHOP) as $number) {
            ShopSection::create(['shop_id' => $this->shopId, 'name' => "Section {$number}", 'position' => $number]);
        }

        $this->actingAs($this->seller)->post(route('seller.products.sections.store'), ['name' => 'One more'])
            ->assertSessionHasErrors(['name' => 'You can have up to 20 shop sections. Delete one to add another.']);
        $this->get(route('seller.products.sections'))->assertOk()->assertSee('20 of 20 used');
    }

    public function test_sellers_cannot_touch_another_shops_sections_or_products(): void
    {
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $theirs = ShopSection::create(['shop_id' => $other->sellerShop()->id, 'name' => 'Theirs']);
        $mine = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'Mine']);
        $theirProduct = Product::create(['seller_id' => $other->id, 'name' => 'Their Mug', 'category' => 'storage_bins', 'price' => 99, 'stock' => 3, 'is_active' => true]);

        $this->actingAs($this->seller);
        $this->put(route('seller.products.sections.update', $theirs), ['name' => 'Taken'])->assertNotFound();
        $this->patch(route('seller.products.sections.move', $theirs), ['direction' => 'up'])->assertNotFound();
        $this->delete(route('seller.products.sections.destroy', $theirs))->assertNotFound();
        $this->put(route('seller.products.sections.update', $mine), ['name' => 'Mine', 'is_visible' => 1, 'products_submitted' => 1, 'product_ids' => [$theirProduct->id]])
            ->assertSessionHasErrors('product_ids.0');
        $this->assertSame('Theirs', $theirs->fresh()->name);
        $this->assertSame(0, $mine->products()->count());
    }

    public function test_section_editor_chooses_products_and_keeps_archived_links(): void
    {
        $section = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'Best Sellers']);
        $sheet = $this->product('Linen Sheet');
        $towel = $this->product('Linen Towel');
        $archived = $this->product('Old Pillowcase');
        $section->products()->attach([$sheet->id, $archived->id]);
        $archived->update(['archived_at' => now(), 'is_active' => false]);

        $this->actingAs($this->seller)->get(route('seller.products.sections'))->assertOk()
            ->assertSee('Best Sellers')->assertSee('1 product')
            ->assertSee('id="section-product-template"', false)->assertSee('Linen Towel')->assertDontSee('Old Pillowcase');

        // Without the product list on the form (no JavaScript), saving leaves the products alone.
        $this->put(route('seller.products.sections.update', $section), ['name' => 'Best Sellers', 'is_visible' => 1])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$sheet->id, $archived->id], $section->products()->pluck('products.id')->all());

        $this->put(route('seller.products.sections.update', $section), ['name' => 'Best Sellers', 'is_visible' => 1, 'products_submitted' => 1, 'product_ids' => [$towel->id]])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$towel->id, $archived->id], $section->products()->pluck('products.id')->all());
    }

    public function test_product_form_picks_sections_without_sending_the_product_back_for_review(): void
    {
        $arrivals = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'New Arrivals']);
        $sale = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'On Sale', 'is_visible' => false]);
        $foreign = ShopSection::create(['shop_id' => $this->shopId + 999, 'name' => 'Elsewhere']);
        $product = $this->product('Linen Sheet');
        $statusBefore = $product->review_status;

        $this->actingAs($this->seller)->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('Shop sections')->assertSee('New Arrivals')->assertSee('On Sale')->assertSee('(hidden)')->assertDontSee('Elsewhere');

        $payload = ['name' => $product->name, 'categories' => ['storage_bins'], 'price' => 500, 'stock' => 5, 'is_active' => 1, 'shop_sections_submitted' => 1];
        $this->put(route('seller.products.update', $product), $payload + ['shop_sections' => [$arrivals->id, $sale->id]])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$arrivals->id, $sale->id], $product->shopSections()->pluck('shop_sections.id')->all());
        $this->assertSame($statusBefore, $product->fresh()->review_status);

        $this->put(route('seller.products.update', $product), $payload + ['shop_sections' => [$foreign->id]])->assertSessionHasErrors('shop_sections.0');

        // Unticking every box clears them; a form without the sections list leaves them alone.
        $this->put(route('seller.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, $product->shopSections()->count());
        $product->shopSections()->attach($arrivals);
        $this->put(route('seller.products.update', $product), array_diff_key($payload, ['shop_sections_submitted' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(1, $product->shopSections()->count());
    }

    public function test_buyers_browse_visible_sections_on_the_shop_page(): void
    {
        $arrivals = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'New Arrivals', 'position' => 2]);
        $bundles = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'Bundle Deals', 'position' => 1]);
        $hidden = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'Secret Stash', 'is_visible' => false]);
        $empty = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'Coming Soon']);
        $sheet = $this->product('Linen Sheet');
        $towel = $this->product('Linen Towel');
        $arrivals->products()->attach($sheet);
        $bundles->products()->attach([$sheet->id, $towel->id]);
        $hidden->products()->attach($towel);

        $this->get(route('shops.show', $this->shopId))->assertOk()
            ->assertSeeInOrder(['All', 'Bundle Deals', 'New Arrivals'])
            ->assertDontSee('Secret Stash')->assertDontSee('Coming Soon')
            ->assertSee('Linen Sheet')->assertSee('Linen Towel');

        $this->get(route('shops.show', [$this->shopId, 'section' => $arrivals->id]))->assertOk()
            ->assertSee('<h2 id="shop-products-heading">New Arrivals', false)
            ->assertSee('Linen Sheet')->assertDontSee('Linen Towel');

        // Hidden, empty or another shop's section: the whole shop shows.
        foreach ([$hidden->id, $empty->id, 999999] as $id) {
            $this->get(route('shops.show', [$this->shopId, 'section' => $id]))->assertOk()->assertSee('Linen Sheet')->assertSee('Linen Towel');
        }
        $this->get(route('shops.show', [$this->shopId, 'section' => 'abc']))->assertRedirect();
    }

    public function test_the_app_gets_sections_and_filters_by_them(): void
    {
        $arrivals = ShopSection::create(['shop_id' => $this->shopId, 'name' => 'New Arrivals']);
        ShopSection::create(['shop_id' => $this->shopId, 'name' => 'Secret Stash', 'is_visible' => false])->products()->attach($this->product('Linen Towel'));
        $arrivals->products()->attach($sheet = $this->product('Linen Sheet'));

        $this->getJson("/api/mobile/shops/{$this->shopId}")->assertOk()
            ->assertJsonPath('shop.sections', [['id' => $arrivals->id, 'name' => 'New Arrivals', 'count' => 1]]);
        $this->getJson("/api/mobile/shops/{$this->shopId}/products?section={$arrivals->id}")->assertOk()
            ->assertJsonCount(1, 'products.data')->assertJsonPath('products.data.0.id', $sheet->id);
        $this->getJson("/api/mobile/shops/{$this->shopId}/products")->assertOk()->assertJsonCount(2, 'products.data');
    }

    private function product(string $name): Product
    {
        return Product::create(['seller_id' => $this->seller->id, 'name' => $name, 'category' => 'storage_bins', 'price' => 500, 'stock' => 5, 'is_active' => true]);
    }
}
