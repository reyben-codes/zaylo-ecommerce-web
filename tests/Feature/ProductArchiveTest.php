<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sellers archive products instead of deleting them, so reviews and order history survive; only archived products
 * nobody ordered or reviewed can be deleted for good.
 */
class ProductArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_hides_a_product_from_buyers_and_restoring_brings_it_back(): void
    {
        [$seller, $tote] = $this->sellerProduct('Canvas Tote');
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $cart = DB::table('carts')->insertGetId(['user_id' => $buyer->id, 'created_at' => now(), 'updated_at' => now()]);
        $variant = $tote->variants()->value('id');
        DB::table('cart_items')->insert(['cart_id' => $cart, 'product_variant_id' => $variant, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($buyer)->post(route('buyer.wishlist.toggle', $tote));

        $this->actingAs($seller)->patch(route('seller.products.archive', $tote))->assertRedirect(route('seller.products'));
        $tote->refresh();
        $this->assertTrue($tote->is_archived);
        $this->assertFalse($tote->is_active);
        // Carts lose it; the wishlist entry is kept but not shown while archived.
        $this->assertDatabaseMissing('cart_items', ['product_variant_id' => $variant]);
        $this->assertDatabaseHas('wishlists', ['product_id' => $tote->id]);

        $this->actingAs($buyer)->get(route('products.show', $tote))->assertNotFound();
        $this->get(route('products.index'))->assertDontSee('Canvas Tote');
        $this->get(route('buyer.wishlist'))->assertOk()->assertDontSee('Canvas Tote');
        $this->post(route('buyer.cart.add', $tote), ['quantity' => 1])->assertNotFound();

        // The seller finds it under Archived, not in the normal list or inventory.
        $this->actingAs($seller)->get(route('seller.products'))->assertOk()->assertDontSee('Canvas Tote')->assertSee('Archived <span>1</span>', false);
        $this->get(route('seller.inventory'))->assertOk()->assertDontSee('Canvas Tote');
        $this->get(route('seller.products', ['view' => 'archived']))->assertOk()->assertSee('Canvas Tote')
            ->assertSee(route('seller.products.restore', $tote));
        $this->get(route('seller.products.edit', $tote))->assertOk()->assertSee('Restore to shop')->assertSee('Delete permanently');

        // Saving the edit form cannot make it visible while archived.
        $this->put(route('seller.products.update', $tote), ['name' => 'Canvas Tote', 'categories' => ['bags'], 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($tote->fresh()->is_active);

        $this->patch(route('seller.products.restore', $tote))->assertRedirect(route('seller.products.edit', $tote));
        $tote->refresh();
        $this->assertFalse($tote->is_archived);
        $this->assertTrue($tote->is_active);
        $this->actingAs($buyer)->get(route('products.show', $tote))->assertOk();
        $this->get(route('buyer.wishlist'))->assertSee('Canvas Tote');
    }

    public function test_only_archived_products_without_orders_or_reviews_can_be_deleted(): void
    {
        [$seller, $draft] = $this->sellerProduct('Test Listing');
        $this->actingAs($seller);

        // Not archived yet: delete is refused.
        $this->delete(route('seller.products.destroy', $draft))->assertSessionHasErrors('product');
        $this->assertModelExists($draft);

        $this->patch(route('seller.products.archive', $draft));
        $this->delete(route('seller.products.destroy', $draft))->assertRedirect(route('seller.products', ['view' => 'archived']));
        $this->assertModelMissing($draft);
    }

    public function test_products_with_orders_or_reviews_stay_archived_with_their_history(): void
    {
        [$seller, $shirt] = $this->sellerProduct('Linen Shirt');
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'ZAY-ARCHIVE', 'total_minor' => 50000, 'payment_method' => 'cod', 'shipping_address' => ['city' => 'Pasig']]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $shirt->seller_id, 'subtotal_minor' => 50000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'delivered']);
        $itemId = DB::table('order_items')->insertGetId([
            'seller_order_id' => $sellerOrder->id, 'product_id' => $shirt->id, 'product_variant_id' => $shirt->variants()->value('id'),
            'product_name' => 'Linen Shirt', 'variant_name' => 'Default', 'unit_price_minor' => 50000, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        ProductReview::create(['order_item_id' => $itemId, 'product_id' => $shirt->id, 'user_id' => $buyer->id, 'rating' => 5, 'comment' => 'Lovely fabric.']);

        $this->actingAs($seller)->patch(route('seller.products.archive', $shirt));
        $this->get(route('seller.products.edit', $shirt))->assertOk()->assertSee('stays archived to keep')
            ->assertDontSee('data-confirm-title="Delete permanently?"', false);
        $this->delete(route('seller.products.destroy', $shirt))->assertSessionHasErrors('product');

        $this->assertModelExists($shirt);
        $this->assertDatabaseHas('product_reviews', ['product_id' => $shirt->id, 'comment' => 'Lovely fabric.']);
        $this->assertDatabaseHas('order_items', ['id' => $itemId, 'product_id' => $shirt->id]);
    }

    public function test_admin_cannot_unhide_a_product_the_seller_archived(): void
    {
        [$seller, $lamp] = $this->sellerProduct('Desk Lamp');
        $this->actingAs($seller)->patch(route('seller.products.archive', $lamp));

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.compliance', ['view' => 'hidden']))->assertOk()->assertSee('Archived by the seller');
        $this->patch(route('admin.compliance.toggle', $lamp))->assertSessionHas('status', fn ($message) => str_contains($message, 'only the seller can restore it'));
        $this->assertFalse($lamp->fresh()->is_active);
    }

    public function test_other_sellers_cannot_archive_or_restore(): void
    {
        [, $tote] = $this->sellerProduct('Canvas Tote');
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->actingAs($other)->patch(route('seller.products.archive', $tote))->assertForbidden();
        $this->patch(route('seller.products.restore', $tote))->assertForbidden();
        $this->assertFalse($tote->fresh()->is_archived);
    }

    private function sellerProduct(string $name): array
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        return [$seller, Product::create(['seller_id' => $seller->id, 'name' => $name, 'category' => 'bags', 'price' => 500, 'stock' => 5, 'is_active' => true])];
    }
}
