<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinalizedCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_finalized_categories_are_offered(): void
    {
        $tree = config('marketplace.category_tree');

        $this->assertSame(
            ['Home and Garden', 'Kitchen Appliances', 'Furniture & Decor', 'Gardening Tools', 'Outdoor Living', 'Home Improvement Tools', 'Bedding & Bath'],
            array_column($tree, 'label'),
        );
        foreach ($tree as $main) {
            $this->assertCount(10, $main['subcategories']);
        }
        $this->assertCount(77, config('marketplace.categories'));
        $this->assertSame(array_keys($tree), array_keys(config('marketplace.browse_categories')));

        $this->get(route('products.index'))->assertOk()
            ->assertSee('<optgroup label="Outdoor Living">', false)
            ->assertSee('<option value="hammocks"', false)
            ->assertDontSee('<option value="electronics"', false);
        $this->get(route('products.index', ['category' => 'electronics']))->assertSessionHasErrors('category');
    }

    public function test_a_product_shows_under_every_category_it_belongs_to(): void
    {
        $cabinet = $this->product('Harris Oak Unit', 'shoe_cabinets', ['shoe_racks']);
        $mixer = $this->product('Vortex Whisk', 'hand_mixers');

        foreach (['shoe_cabinets', 'shoe_racks', 'furniture_decor', 'home_garden'] as $category) {
            $this->get(route('products.index', ['category' => $category]))->assertOk()
                ->assertSee($cabinet->name)->assertDontSee($mixer->name);
        }
        $this->get(route('products.index', ['category' => 'kitchen_appliances']))->assertOk()
            ->assertSee($mixer->name)->assertDontSee($cabinet->name);
        $this->getJson('/api/mobile/products?category=home_garden')->assertOk()
            ->assertJsonFragment(['name' => $cabinet->name])->assertJsonMissing(['name' => $mixer->name]);
    }

    public function test_sellers_tick_subcategories_grouped_by_main_category(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Seller::create(['user_id' => $seller->id, 'name' => 'Shop', 'slug' => 'shop', 'status' => 'approved']);

        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()
            ->assertSeeInOrder(['Home and Garden', 'value="storage_bins"', 'Bedding &amp; Bath', 'value="bath_mats"'], false)
            ->assertDontSee('value="shoes"', false);
    }

    public function test_products_with_a_removed_category_stay_readable_without_a_broken_link(): void
    {
        $product = $this->product('Old Sneaker', 'shoes');

        $html = view('partials.category-tags', ['product' => $product, 'href' => fn ($key) => '/filter/'.$key])->render();

        $this->assertStringContainsString('<span class="category-tag">Shoes</span>', $html);
        $this->assertStringNotContainsString('/filter/shoes', $html);
    }

    public function test_migration_moves_known_products_and_leaves_the_rest(): void
    {
        // Production keeps the category key in a column on products, which this test schema does not have.
        Schema::table('products', fn (Blueprint $table) => $table->string('category')->nullable());
        $place = function (string $name, string $category, array $extras = []): int {
            $product = $this->product($name, 'bedsheets', $extras);
            DB::table('products')->where('id', $product->id)->update(['category' => $category, 'updated_at' => '2026-01-01 00:00:00']);

            return $product->id;
        };
        $cooker = $place('Elite Gourmet Rice Cooker', 'electronics', ['kitchen_dining']);
        $cabinet = $place('Harris Three-Door Shoe Cabinet', 'home_living');
        $sneaker = $place('Nike Dunk Low', 'shoes', ['clothing']);
        $application = fn (string $category) => SellerApplication::create([
            'user_id' => User::factory()->create()->id, 'shop_name' => 'Shop '.$category, 'shop_category' => $category, 'seller_type' => 'individual', 'tin' => fake()->unique()->numerify('###-###-###-000'),
            'region_code' => '130000000', 'region_name' => 'NCR', 'city_code' => '137404000', 'city_name' => 'Quezon City', 'barangay_code' => '137404001', 'barangay_name' => 'Bagong Pag-asa',
            'street_address' => '12 Sampaguita St', 'postal_code' => '1105', 'status' => 'pending',
        ])->id;
        $homeShop = $application('home_living');
        $toyShop = $application('toys_hobbies');

        (require database_path('migrations/2026_11_01_000001_move_products_to_finalized_categories.php'))->up();

        $row = fn (int $id) => [DB::table('products')->where('id', $id)->value('category'), DB::table('product_categories')->where('product_id', $id)->pluck('category')->all()];
        $this->assertSame(['rice_cookers', []], $row($cooker));
        $this->assertSame(['shoe_cabinets', ['shoe_racks']], $row($cabinet));
        $this->assertSame(['shoes', ['clothing']], $row($sneaker));
        $this->assertSame('2026-01-01 00:00:00', DB::table('products')->where('id', $cooker)->value('updated_at'));
        $this->assertSame('home_garden', DB::table('seller_applications')->where('id', $homeShop)->value('shop_category'));
        $this->assertSame('toys_hobbies', DB::table('seller_applications')->where('id', $toyShop)->value('shop_category'));
    }

    public function test_every_placement_uses_a_finalized_subcategory(): void
    {
        $migration = require database_path('migrations/2026_11_01_000001_move_products_to_finalized_categories.php');
        $subcategories = collect(config('marketplace.category_tree'))->flatMap(fn (array $main) => array_keys($main['subcategories']))->all();

        foreach ($migration::PLACEMENTS as $name => $categories) {
            $this->assertSame([], array_diff($categories, $subcategories), $name);
        }
    }

    private function product(string $name, string $category, array $extras = []): Product
    {
        $seller = Seller::first() ?? Seller::create([
            'user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id,
            'name' => 'Shop', 'slug' => 'shop-'.uniqid(), 'status' => 'approved',
        ]);
        $product = Product::create(['seller_id' => $seller->user_id, 'name' => $name, 'category' => $category, 'price' => 500, 'stock' => 5, 'is_active' => true]);
        foreach ($extras as $extra) {
            ProductCategory::create(['product_id' => $product->id, 'category' => $extra]);
        }

        return $product->fresh();
    }
}
