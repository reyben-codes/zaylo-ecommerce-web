<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        return Product::create(array_merge(['seller_id' => $seller->id, 'name' => 'Classic shoe', 'category' => 'shoes', 'price' => 1000, 'stock' => 10, 'is_active' => true], $attributes));
    }

    public function test_search_matches_multiple_words_across_fields_and_prioritizes_exact_names(): void
    {
        $exact = $this->product(['name' => 'Blue shoes']);
        $other = $this->product(['name' => 'Leather shoes', 'description' => 'Available in blue', 'sku' => 'BLUE-42']);
        $this->product(['name' => 'Red shoes']);
        $this->product(['name' => 'Blue shoes hidden', 'is_active' => false]);
        $this->get(route('products.index', ['search' => '  Blue   shoes ']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$exact->id, $other->id])
            ->assertSee('Remove Search: Blue shoes')->assertSeeText('2 products found');
        $this->get(route('products.index', ['search' => 'blue-42']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$other->id]);
    }

    public function test_search_as_you_type_suggests_partial_word_matches_as_json(): void
    {
        $exact = $this->product(['name' => 'Blue', 'price' => 499]);
        $partial = $this->product(['name' => 'Bluetooth speaker']);
        $this->product(['name' => 'Red shoes']);
        $this->product(['name' => 'Blue hidden', 'is_active' => false]);
        foreach (range(1, 6) as $number) $this->product(['name' => "Blueberry jam {$number}"]);

        // Partial words match; an exact name ranks first, as in the catalog.
        $suggestions = $this->getJson(route('products.index', ['search' => 'blue', 'suggest' => 1]))->assertOk()->json('products');
        $this->assertCount(6, $suggestions);
        $this->assertSame(route('products.show', $exact), $suggestions[0]['url']);
        $this->assertSame('₱499.00', $suggestions[0]['price']);
        $this->assertNotContains('Blue hidden', array_column($suggestions, 'name'));

        $this->getJson(route('products.index', ['search' => 'bluet', 'suggest' => 1]))->assertOk()
            ->assertExactJson(['products' => [[
                'name' => 'Bluetooth speaker', 'url' => route('products.show', $partial),
                'image' => asset('images/ZAYLO_ICON_DARK.png'), 'price' => '₱1,000.00', 'sold_out' => false,
            ]]]);
    }

    public function test_catalog_page_marks_the_results_region_that_live_search_refreshes(): void
    {
        $this->product(['name' => 'Blue shoes']);
        $this->get(route('products.index', ['search' => 'blu']))->assertOk()
            ->assertSee('data-live-catalog', false)
            ->assertSeeInOrder(['data-live-catalog-results', '<strong>1</strong> product found', 'Blue shoes'], false);
    }

    public function test_search_treats_wildcards_as_literal_text_and_escapes_html(): void
    {
        $matching = $this->product(['name' => '100% cotton_shoe']);
        $this->product(['name' => '100 cotton shoe']);
        foreach (['%', '_', '100%'] as $search) {
            $this->get(route('products.index', ['search' => $search]))->assertOk()
                ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$matching->id]);
        }
        $this->get(route('products.index', ['search' => '<script>alert(1)</script>']))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_price_range_and_sale_filters_use_active_discounted_prices(): void
    {
        $this->travelTo('2026-10-11');
        $sale = $this->product(['sale_type' => 'percent', 'sale_value' => 20, 'sale_starts_at' => '2026-10-10', 'sale_ends_at' => '2026-10-15']);
        $this->product(['price' => 1100]);
        $this->get(route('products.index', ['min_price' => '800.00', 'max_price' => '800.00', 'on_sale' => 1]))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$sale->id]);
        $this->travelTo('2026-10-16');
        $this->get(route('products.index', ['on_sale' => 1]))->assertOk()->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_price_sorting_uses_sale_prices_and_newest_sort_uses_creation_time(): void
    {
        $sale = $this->product(['price' => 1000, 'sale_type' => 'percent', 'sale_value' => 50, 'sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->addDay(), 'created_at' => now()->subDays(2)]);
        $regular = $this->product(['price' => 750, 'created_at' => now()->subDay()]);
        $newest = $this->product(['price' => 1200]);
        foreach ([
            'price_low' => [$sale->id, $regular->id, $newest->id],
            'price_high' => [$newest->id, $regular->id, $sale->id],
            'newest' => [$newest->id, $regular->id, $sale->id],
        ] as $sort => $expected) {
            $this->get(route('products.index', ['sort' => $sort]))->assertOk()
                ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === $expected);
        }
    }

    public function test_stock_filters_and_sold_out_detail_page_are_consistent(): void
    {
        $available = $this->product();
        $sold = $this->product(['stock' => 0]);
        // Everything is listed by default, available products first even though the sold-out one is newer.
        $this->get(route('products.index'))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$available->id, $sold->id]);
        $this->get(route('products.index', ['availability' => 'in_stock']))->assertOk()->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$available->id]);
        $this->get(route('products.index', ['availability' => 'out_of_stock']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$sold->id])->assertSee('Currently sold out');
        $this->get(route('products.index', ['availability' => 'all']))->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 2);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('products.show', $sold))->assertOk()->assertSee('Currently sold out')->assertDontSee('class="market-form product-buy-form"', false);
        $this->post(route('buyer.cart.add', $sold), ['quantity' => 1])->assertSessionHasErrors('quantity');
    }

    public function test_secondary_in_stock_variation_is_browsable_even_if_default_is_empty(): void
    {
        $product = $this->product(['stock' => 0]);
        $product->variants()->create(['sku' => 'SECOND', 'name' => 'Second option', 'price_minor' => 120000, 'stock' => 3, 'is_active' => true]);
        $this->get(route('products.index'))->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 1);
        $this->get(route('products.show', $product))->assertOk()->assertSee('3 available');
    }

    public function test_chips_remove_only_one_filter_and_pagination_preserves_the_others(): void
    {
        foreach (range(1, 16) as $i) $this->product(['name' => 'Blue shoe '.$i, 'gender' => 'women']);
        $filters = ['search' => 'blue', 'gender' => 'women', 'category' => 'fashion', 'min_price' => 0, 'sort' => 'price_low'];
        $response = $this->get(route('products.index', $filters))->assertOk();
        $pagination = $response->viewData('products');
        parse_str(parse_url($pagination->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('2', $query['page']);
        $this->assertSame('women', $query['gender']);
        $this->assertSame('0', $query['min_price']);
        $chip = collect($response->viewData('filterChips'))->firstWhere('label', 'Women');
        parse_str(parse_url($chip['url'], PHP_URL_QUERY), $remaining);
        $this->assertArrayNotHasKey('gender', $remaining);
        $this->assertArrayNotHasKey('page', $remaining);
        $this->assertSame('blue', $remaining['search']);
        $this->get($pagination->nextPageUrl())->assertOk()->assertViewHas('products', fn ($products) => $products->count() === 1);
    }

    public function test_unisex_products_appear_under_men_and_women(): void
    {
        $this->product(['name' => 'Unisex sneaker', 'gender' => 'unisex']);
        $this->product(['name' => 'Mens loafer', 'gender' => 'men']);
        $this->product(['name' => 'Womens heel', 'gender' => 'women']);

        $this->get(route('products.index', ['gender' => 'men']))->assertOk()
            ->assertSee('Unisex sneaker')->assertSee('Mens loafer')->assertDontSee('Womens heel');
        $this->get(route('products.index', ['gender' => 'women']))->assertOk()
            ->assertSee('Unisex sneaker')->assertSee('Womens heel')->assertDontSee('Mens loafer');
        $this->get(route('products.index', ['gender' => 'unisex']))->assertOk()
            ->assertSee('Unisex sneaker')->assertDontSee('Mens loafer')->assertDontSee('Womens heel');
    }

    public function test_invalid_filter_inputs_are_rejected(): void
    {
        foreach ([['min_price' => -1], ['min_price' => 500, 'max_price' => 100], ['availability' => 'invalid'], ['sort' => 'invalid'], ['search' => ['invalid']], ['on_sale' => 'invalid']] as $filters) {
            $this->getJson(route('products.index', $filters))->assertUnprocessable();
        }
    }

    public function test_buyer_cards_show_escaped_descriptions_on_all_browsing_pages(): void
    {
        $description = 'Comfortable shoes <script>alert(1)</script> '.str_repeat('Everyday comfort. ', 30);
        $this->product(['description' => $description]);
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        foreach (['home', 'products.index', 'buyer.dashboard'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('class="product-card-description"', false)
                ->assertSee($description)->assertDontSee('<script>alert(1)</script>', false);
        }
    }

    public function test_secondary_filters_are_disclosed_without_hiding_applied_filter_state(): void
    {
        $response = $this->get(route('products.index', ['min_price' => 0, 'gender' => 'women', 'on_sale' => 1]))->assertOk();
        $response->assertSee('<details class="catalog-more-filters" data-smooth-disclosure>', false)
            ->assertSeeText('3 active')->assertSee('Remove From ₱0.00')
            ->assertSee('Remove Women')->assertSee('Remove On sale')->assertSeeText('Show products');
        $this->get(route('products.index'))->assertOk()->assertDontSee('class="catalog-filter-count"', false);
    }

    public function test_cards_reserve_description_space_even_when_description_is_missing(): void
    {
        foreach ([null, '', 'Short description', str_repeat('Long description. ', 40)] as $description) {
            $this->product(['description' => $description]);
        }
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        foreach (['home', 'products.index', 'buyer.dashboard'] as $route) {
            $response = $this->get(route($route))->assertOk();
            $this->assertSame(4, substr_count($response->getContent(), 'class="product-card-description"'));
            $this->assertSame(2, substr_count($response->getContent(), 'class="product-card-description">No Description</p>'));
        }
    }
}
