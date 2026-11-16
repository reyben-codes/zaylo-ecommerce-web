<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductCategoryChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProductCategoryChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_distinguish_devices_accessories_and_unknown_titles(): void
    {
        foreach (['Apple iPhone 17 Pro' => 'mobile_phones', 'iPhone 17 case' => 'phone_accessories', 'Case for iPhone 17' => 'phone_accessories',
            'MacBook Air' => 'laptops', 'MacBook charger' => 'computer_accessories', 'Apple iPad' => 'tablets', 'Apple storage basket' => null] as $name => $expected) {
            $this->assertSame($expected, ProductCategoryChecks::suggestion($name), $name);
        }
        $this->assertNull(ProductCategoryChecks::mismatch('iPhone 17', ['mobile_phones']));
        $this->assertNotNull(ProductCategoryChecks::mismatch('iPhone 17', ['mobile_phones', 'home_garden']));
        $this->assertNotNull(ProductCategoryChecks::mismatch('iPhone 17', ['mobile_phones'], ['home_garden' => ['Phones']]));
        $this->assertNull(ProductCategoryChecks::mismatch('Unknown handmade item', ['home_garden']));
    }

    public function test_admin_must_correct_a_detected_mismatch_before_approving(): void
    {
        Notification::fake();
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($seller)->post(route('seller.products.store'), ['name' => 'Apple iPhone 17 Pro', 'category' => 'home_garden', 'price' => 50000, 'stock' => 9])
            ->assertSessionHasNoErrors();
        $product = Product::where('name', 'Apple iPhone 17 Pro')->firstOrFail();
        $this->assertTrue($product->is_pending_review);
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('Category mismatch')->assertSee('Mobile Phones')->assertSee('data-category-suggestion', false);
        $this->patch(route('admin.compliance.category', $product), ['category' => 'mobile_phones'])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.product-approvals'))->assertOk()->assertSee('Category mismatch')->assertSee('Correct category');
        $this->patch(route('admin.compliance.review', $product), ['decision' => 'approve'])->assertSessionHasErrors('category');
        $this->assertTrue($product->fresh()->is_pending_review);
        $stock = $product->stock;
        $price = $product->price;
        $this->patch(route('admin.compliance.category', $product), ['category' => 'mobile_phones'])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame('mobile_phones', $product->category_key);
        $this->assertSame($price, $product->price);
        $this->assertSame($stock, $product->stock);
        $this->assertTrue($product->is_pending_review);
        $this->assertDatabaseHas('account_updates', ['user_id' => $seller->id, 'title' => 'Product category corrected']);
        $this->patch(route('admin.compliance.review', $product), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->is_listed);
        $this->get(route('products.index', ['category' => 'electronics']))->assertOk()->assertSee($product->name);
        $this->getJson('/api/mobile/products?category=mobile_phones')->assertOk()->assertJsonFragment(['name' => $product->name]);
        $this->getJson('/api/mobile/products?category=home_garden')->assertOk()->assertJsonMissing(['name' => $product->name]);
    }

    public function test_phone_repair_preserves_prices_stock_and_accessory_categories(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $phone = Product::create(['seller_id' => $seller->id, 'name' => 'iPhone 16', 'category' => 'home_garden', 'price' => 50000, 'stock' => 7]);
        $case = Product::create(['seller_id' => $seller->id, 'name' => 'iPhone 16 Case', 'category' => 'phone_accessories', 'price' => 500, 'stock' => 8]);
        $phone->syncAdditionalCategories(['storage_bins']);
        $price = $phone->price;
        $stock = $phone->stock;
        $variants = $phone->variants()->get()->toJson();
        $backups = glob(storage_path('app/private/category-repairs/*.json')) ?: [];
        try {
            $this->artisan('catalog:repair-phone-categories')->expectsOutputToContain('Preview only')->assertSuccessful();
            $this->assertSame('home_garden', $phone->fresh()->category_key);
            $this->artisan('catalog:repair-phone-categories --execute')->assertSuccessful();
            $phone->refresh();
            $this->assertSame(['mobile_phones'], $phone->category_keys);
            $this->assertSame($price, $phone->price);
            $this->assertSame($stock, $phone->stock);
            $this->assertSame($variants, $phone->variants()->get()->toJson());
            $this->assertSame('phone_accessories', $case->fresh()->category_key);
            $this->assertTrue($phone->is_pending_review);
            $this->artisan('catalog:repair-phone-categories --execute')->expectsOutputToContain('No misplaced')->assertSuccessful();
            $this->assertCount(1, array_diff(glob(storage_path('app/private/category-repairs/*.json')) ?: [], $backups));
        } finally {
            foreach (array_diff(glob(storage_path('app/private/category-repairs/*.json')) ?: [], $backups) as $path) unlink($path);
        }
    }
}
