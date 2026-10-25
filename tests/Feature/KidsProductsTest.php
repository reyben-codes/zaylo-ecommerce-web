<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kids products: sellers mark them for Girls, Boys or Kids (boys or girls); buyers find them with the For filter,
 * the Kids link, kid words in search, and a size filter.
 */
class KidsProductsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
    }

    public function test_the_for_filter_keeps_kids_and_adult_items_apart(): void
    {
        foreach (['Girls Tutu Dress' => 'girls', 'Boys Dino Tee' => 'boys', 'Kids Rain Boots' => 'kids', 'Womens Blouse' => 'women', 'Mens Polo' => 'men', 'Unisex Hoodie' => 'unisex'] as $name => $gender) {
            $this->product($name, $gender);
        }
        $expect = [
            'kids' => ['Girls Tutu Dress', 'Boys Dino Tee', 'Kids Rain Boots'],
            'girls' => ['Girls Tutu Dress', 'Kids Rain Boots'],
            'boys' => ['Boys Dino Tee', 'Kids Rain Boots'],
            'women' => ['Womens Blouse', 'Unisex Hoodie'],
            'men' => ['Mens Polo', 'Unisex Hoodie'],
        ];
        $all = ['Girls Tutu Dress', 'Boys Dino Tee', 'Kids Rain Boots', 'Womens Blouse', 'Mens Polo', 'Unisex Hoodie'];

        foreach ($expect as $gender => $shown) {
            $response = $this->get(route('products.index', ['gender' => $gender]))->assertOk();
            foreach ($all as $name) {
                in_array($name, $shown, true) ? $response->assertSee($name) : $response->assertDontSee($name);
            }
        }
        $this->get(route('products.index', ['gender' => 'adults']))->assertSessionHasErrors('gender');
    }

    public function test_buyers_reach_kids_items_from_the_menu_and_by_searching(): void
    {
        $this->product('Tutu Dress', 'girls');
        $this->product('Leather Belt', 'men');

        $this->get(route('home'))->assertOk()->assertSee(route('products.index', ['gender' => 'kids']));
        foreach (['kids', 'toddler', 'girls'] as $word) {
            $this->get(route('products.index', ['search' => $word]))->assertOk()->assertSee('Tutu Dress')->assertDontSee('Leather Belt');
        }
        $this->get(route('products.show', Product::where('name', 'Tutu Dress')->first()))->assertSee('For girls');
    }

    public function test_buyers_filter_by_size(): void
    {
        $dress = $this->product('Tutu Dress', 'girls');
        $dress->variants()->create(['sku' => 'TUTU-4T', 'name' => '4T', 'options' => ['size' => '4T'], 'price_minor' => 50000, 'stock' => 3, 'is_active' => true]);
        $shirt = $this->product('Linen Shirt', 'men');
        $shirt->variants()->create(['sku' => 'LIN-M', 'name' => 'M', 'options' => ['size' => 'M'], 'price_minor' => 90000, 'stock' => 2, 'is_active' => true]);

        $page = $this->get(route('products.index'))->assertOk();
        // Kids' sizes are listed before adult sizes.
        $this->assertSame(['4T', 'M'], $page->viewData('sizes'));
        $page->assertSee('<option value="4T"', false);

        $this->get(route('products.index', ['size' => '4T']))->assertOk()->assertSee('Tutu Dress')->assertDontSee('Linen Shirt')->assertSee('Size: 4T');
        $this->get(route('products.index', ['size' => '4T', 'gender' => 'boys']))->assertOk()->assertDontSee('Tutu Dress');
    }

    public function test_sellers_mark_kids_items_and_get_kids_standard_sizes(): void
    {
        $this->actingAs($this->seller)->get(route('seller.products.create'))->assertOk()
            ->assertSee('<option value="girls"', false)->assertSee('Kids (boys or girls)')
            ->assertSee('data-kids-size-presets=', false);

        $this->post(route('seller.products.store'), ['name' => 'Dino Sneakers', 'categories' => ['shoes'], 'gender' => 'boys', 'price' => 900, 'stock' => 4, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $sneakers = Product::where('name', 'Dino Sneakers')->firstOrFail();
        $this->assertSame('boys', $sneakers->gender);
        $this->assertSame(config('marketplace.kids_size_presets.shoes'), $sneakers->sizePresets());
        $this->get(route('seller.products.edit', $sneakers))->assertOk()->assertSee('data-fill-sizes="'.e(json_encode(config('marketplace.kids_size_presets.shoes'))).'"', false);

        $adult = $this->product('Court Sneakers', 'men', 'shoes');
        $this->assertSame(config('marketplace.size_presets.shoes'), $adult->sizePresets());
        $this->post(route('seller.products.store'), ['name' => 'Bad', 'categories' => ['shoes'], 'gender' => 'pets', 'price' => 1, 'stock' => 1])->assertSessionHasErrors('gender');
    }

    private function product(string $name, string $gender, string $category = 'clothing'): Product
    {
        return Product::create(['seller_id' => $this->seller->id, 'name' => $name, 'category' => $category, 'gender' => $gender, 'price' => 500, 'stock' => 5, 'is_active' => true]);
    }
}
