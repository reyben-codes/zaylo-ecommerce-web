<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScheduledProductSaleTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller);

        return Product::create(['seller_id' => $seller->id, 'name' => 'Scheduled shoe', 'category' => 'shoes', 'price' => 1000, 'stock' => 10, 'is_active' => true]);
    }

    private function sale(array $overrides = []): array
    {
        return array_merge(['sale_type' => 'percent', 'sale_value' => 20, 'sale_starts_at' => '2026-10-10T08:00', 'sale_ends_at' => '2026-10-15T08:00'], $overrides);
    }

    public function test_schedule_uses_philippine_time_and_preserves_regular_price_and_stock(): void
    {
        $product = $this->product();
        $this->put(route('seller.products.sale', $product), $this->sale())->assertRedirect()->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame('2026-10-10 00:00:00', $product->sale_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('1000.00', $product->regular_price);
        $this->assertSame(10, $product->stock);
        $this->travelTo('2026-10-09 23:59:59');
        $this->assertSame('1000.00', $product->price);
        $this->assertNull($product->original_price);
        $this->travelTo('2026-10-10 00:00:00');
        $this->assertSame('800.00', $product->price);
        $this->assertSame('1000.00', $product->original_price);
        $this->assertSame(20, $product->discount_percentage);
        $this->assertSame('Sale', $product->badge);
        $this->travelTo('2026-10-15 00:00:00');
        $this->assertSame('1000.00', $product->price);
        $this->assertNull($product->original_price);
        $this->assertNull($product->badge);
        $this->get(route('seller.products'))->assertOk()->assertSee('Manage sale')->assertSee('Regular price')->assertSee('data-price="1000.00"', false);
    }

    public function test_fixed_price_targets_only_selected_variation_and_cart_uses_it(): void
    {
        $product = $this->product();
        $variant = $product->variants()->create(['sku' => 'SECOND', 'name' => 'Blue', 'price_minor' => 120000, 'stock' => 5, 'is_active' => true]);
        $this->put(route('seller.products.sale', $product), $this->sale(['sale_type' => 'price', 'sale_value' => 900, 'sale_variant_id' => $variant->id]))->assertSessionHasNoErrors();
        $this->travelTo('2026-10-11');
        $product->refresh();
        $variant->refresh();
        $this->assertSame('1000.00', $product->price);
        $this->assertSame(900.0, $variant->price);
        $cartItem = (new CartItem)->setRelation('product', $product)->setRelation('variant', $variant);
        $this->assertSame(900.0, $cartItem->unitPrice());
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('products.show', $product))->assertOk()->assertSee('data-price="900"', false)->assertSee('data-regular="1200"', false);
        $snapshot = new OrderItem(['unit_price_minor' => 90000, 'quantity' => 2]);
        $this->travelTo('2026-10-16');
        $this->assertSame(1200.0, $cartItem->unitPrice());
        $this->assertSame(900.0, $snapshot->unit_price);
    }

    public function test_invalid_discounts_dates_and_other_sellers_are_rejected(): void
    {
        $product = $this->product();
        foreach ([['sale_value' => 100], ['sale_value' => -1], ['sale_value' => 20.123], ['sale_type' => 'price', 'sale_value' => 1000], ['sale_ends_at' => '2026-10-09T08:00'], ['sale_starts_at' => null]] as $invalid) {
            $this->putJson(route('seller.products.sale', $product), $this->sale($invalid))->assertUnprocessable();
        }
        $other = $this->product();
        $this->putJson(route('seller.products.sale', $product), $this->sale())->assertForbidden();
        $this->putJson(route('seller.products.sale', $other), $this->sale(['sale_variant_id' => $product->defaultVariant->id]))->assertUnprocessable();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->putJson(route('seller.products.sale', $other), $this->sale())->assertForbidden();
    }

    public function test_sale_can_be_cancelled_and_effective_prices_are_used_for_sorting(): void
    {
        $product = $this->product();
        $this->put(route('seller.products.sale', $product), $this->sale())->assertSessionHasNoErrors();
        $this->travelTo('2026-10-11');
        $other = $this->product();
        $other->update(['price' => 900, 'stock' => 10]);
        $this->get(route('products.index', ['sort' => 'price_low']))->assertOk()->assertViewHas('products', fn ($products) => $products->first()->id === $product->id);
        $this->get(route('products.index', ['sort' => 'price_high']))->assertOk()->assertViewHas('products', fn ($products) => $products->first()->id === $other->id);
        $this->actingAs($product->seller->owner);
        $this->put(route('seller.products.sale', $product), ['sale_type' => null])->assertSessionHasNoErrors();
        $this->assertSame('1000.00', $product->fresh()->price);
        $this->assertNull($product->fresh()->original_price);
    }

    public function test_legacy_product_prices_and_sale_queries_use_the_same_schedule(): void
    {
        $product = $this->product();
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
        });
        DB::table('products')->where('id', $product->id)->update(['price' => 1000, 'sale_type' => 'price', 'sale_value' => 750, 'sale_starts_at' => '2026-10-10 00:00:00', 'sale_ends_at' => '2026-10-15 00:00:00']);
        $this->travelTo('2026-10-11');
        $this->assertSame('750.00', $product->fresh()->price);
        $this->assertSame('1000.00', $product->fresh()->regular_price);
        $effective = Product::selectRaw(Product::sellingPriceSql().' as computed', [now(), now()])->first()->computed;
        $this->assertEquals(750, $effective);
        $this->travelTo('2026-10-16');
        $this->assertSame('1000.00', $product->fresh()->price);
    }

    public function test_buyer_deals_only_include_current_sales(): void
    {
        $product = $this->product();
        $this->put(route('seller.products.sale', $product), $this->sale())->assertSessionHasNoErrors();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer);
        $this->travelTo('2026-10-09');
        $this->get(route('buyer.dashboard'))->assertOk()->assertViewHas('flashProducts', fn ($items) => $items->isEmpty());
        $this->travelTo('2026-10-11');
        $this->get(route('buyer.dashboard'))->assertOk()->assertViewHas('flashProducts', fn ($items) => $items->first()->id === $product->id)->assertDontSee('flashEndTime');
        $this->travelTo('2026-10-16');
        $this->get(route('buyer.dashboard'))->assertOk()->assertViewHas('flashProducts', fn ($items) => $items->isEmpty());
    }
}
