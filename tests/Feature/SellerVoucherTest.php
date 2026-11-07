<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherSeller;
use App\Notifications\VoucherInvitation;
use App\Services\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PsgcFixtures;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Discount vouchers are offered to every shop by notification and email; shops that join have the discount on their
 * products and pay it, shops that decline are left out. Global free shipping needs no opt-in (ZAYLO pays it).
 */
class SellerVoucherTest extends TestCase
{
    use PsgcFixtures, RefreshDatabase, SignsInMobile;

    public function test_shops_opt_in_and_only_their_products_get_the_discount(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $joins = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Linen House']);
        $declines = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Gadget Hub']);
        $lamp = Product::create(['seller_id' => $joins->id, 'name' => 'Rattan Lamp', 'category' => 'storage_bins', 'price' => 1000, 'stock' => 5, 'is_active' => true]);
        $cable = Product::create(['seller_id' => $declines->id, 'name' => 'USB Cable', 'category' => 'storage_bins', 'price' => 400, 'stock' => 5, 'is_active' => true]);

        // The admin creates a seller-funded 10% voucher: every shop is invited by email.
        $this->actingAs($admin)->get(route('admin.vouchers'))->assertOk()->assertSee('Create and invite shops');
        $this->post(route('admin.vouchers.store'), ['code' => 'payday10', 'name' => 'Payday Sale', 'type' => 'percent', 'value' => 10, 'max_discount' => 150, 'min_spend' => 500])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($status) => str_contains($status, 'Every shop was invited'));
        $voucher = Voucher::where('code', 'PAYDAY10')->firstOrFail();
        $this->assertSame(['seller', 2], [$voucher->funded_by, VoucherSeller::where('voucher_id', $voucher->id)->where('status', 'pending')->count()]);
        Notification::assertSentTo($joins, VoucherInvitation::class, fn ($mail) => str_contains($mail->toMail($joins)->subject, 'PAYDAY10'));

        // Sellers see it with a counter, and answer.
        $this->actingAs($joins)->get(route('seller.dashboard'))->assertSee('data-sidebar-count="vouchers" data-sidebar-count-label="to answer" >1', false);
        $this->get(route('seller.vouchers'))->assertOk()->assertSee('PAYDAY10')->assertSee('10% off, up to ₱150.00, min. spend ₱500.00');
        $this->patch(route('seller.vouchers.respond', $voucher), ['decision' => 'join'])->assertSessionHasNoErrors();
        $this->actingAs($declines)->patch(route('seller.vouchers.respond', $voucher), ['decision' => 'decline'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.vouchers'))->assertSee('1 joined')->assertSee('1 declined');

        // Only the shop that joined gives the discount: 10% of ₱1,000 = ₱100 (under the ₱150 cap); nothing off the cable.
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $buyer->addresses()->create($this->savedAddressData(['is_default' => true]));
        $this->actingAs($buyer)->post(route('buyer.cart.add', $lamp), ['quantity' => 1]);
        $this->post(route('buyer.cart.add', $cable), ['quantity' => 1]);
        $items = $buyer->cart()->first()->items()->with('product')->get();
        $this->assertSame([(int) $lamp->fresh()->seller_id => 100.0], app(Vouchers::class)->cartDiscounts($voucher->fresh(), $items));
        $this->post(route('buyer.cart.selection'), ['action' => 'checkout', 'cart_item_ids' => $items->pluck('id')->all()]);
        $this->get(route('buyer.checkout.show', ['voucher' => 'payday10']))->assertOk()
            ->assertSee('PAYDAY10 applied: 10% off')->assertSee('Voucher PAYDAY10')->assertSee('data-voucher-discount="100"', false);

        // The app quote has the same discount.
        $this->signInMobile($buyer);
        $this->postJson('/api/mobile/checkout/quote', ['cart_item_ids' => $items->pluck('id')->all(), 'voucher_code' => 'PAYDAY10'])->assertOk()
            ->assertJsonPath('totals.voucher_discount', 100)->assertJsonPath('voucher_label', '10% off, up to ₱150.00, min. spend ₱500.00');
        // A cart with only the declining shop gets nothing, and is told why.
        $this->postJson('/api/mobile/checkout/quote', ['cart_item_ids' => [$items->firstWhere('product.id', $cable->id)->id], 'voucher_code' => 'PAYDAY10'])
            ->assertUnprocessable()->assertJsonPath('errors.voucher_code.0', 'None of the shops in your order joined this voucher.');
    }

    public function test_discount_rules_and_global_free_shipping(): void
    {
        $vouchers = app(Vouchers::class);
        $shop = 7;
        $percent = new Voucher(['type' => 'percent', 'value' => 20, 'max_discount' => 50, 'min_spend' => 300]);
        $this->assertSame(50.0, $vouchers->discountFor($percent, $shop, 1000, [$shop]), 'Capped at the most it can take off.');
        $this->assertSame(0.0, $vouchers->discountFor($percent, $shop, 200, [$shop]), 'Below the minimum spend.');
        $this->assertSame(0.0, $vouchers->discountFor($percent, $shop, 1000, [8]), 'Shop did not join.');
        $fixed = new Voucher(['type' => 'fixed', 'value' => 80]);
        $this->assertSame(60.0, $vouchers->discountFor($fixed, $shop, 60, [$shop]), 'Never more than the items cost.');

        // Free shipping applies everywhere without invitations; ZAYLO pays it.
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        User::factory()->create(['role' => 'seller', 'status' => 'active'])->sellerShop();
        Notification::fake();
        $this->actingAs($admin)->post(route('admin.vouchers.store'), ['code' => 'SHIPFREE', 'type' => 'free_shipping'])->assertSessionHasNoErrors();
        $free = Voucher::where('code', 'SHIPFREE')->firstOrFail();
        $this->assertSame(['zaylo', 0], [$free->funded_by, VoucherSeller::where('voucher_id', $free->id)->count()]);
        Notification::assertNothingSent();
        $this->assertSame(0.0, $vouchers->discountFor($free, $shop, 1000));
    }

    public function test_admin_can_target_voucher_to_a_specific_seller(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $sellerA = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Artisan Pottery']);
        $shopA = $sellerA->sellerShop();
        $sellerB = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Boutique Crafts']);
        $sellerB->sellerShop();

        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'code' => 'ARTISAN15',
            'name' => 'Exclusive for Artisan',
            'target_type' => 'specific',
            'target_seller_id' => $shopA->id,
            'type' => 'percent',
            'value' => 15,
            'min_spend' => 200,
        ])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($status) => str_contains($status, 'was created for'));

        $voucher = Voucher::where('code', 'ARTISAN15')->firstOrFail();
        $this->assertSame((int) $shopA->id, (int) $voucher->seller_id);
        $this->assertSame(1, VoucherSeller::where('voucher_id', $voucher->id)->count());
        $this->assertSame((int) $shopA->id, (int) VoucherSeller::where('voucher_id', $voucher->id)->value('seller_id'));

        Notification::assertSentTo($sellerA, VoucherInvitation::class);
        Notification::assertNotSentTo($sellerB, VoucherInvitation::class);

        $this->actingAs($admin)->get(route('admin.vouchers'))
            ->assertOk()
            ->assertSee('Specific:');
    }

    public function test_seller_can_create_and_manage_own_shop_voucher(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Sole Seller']);
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Other Seller']);

        // Seller creates their own shop voucher.
        $this->actingAs($seller)->post(route('seller.vouchers.store'), [
            'code' => 'SOLE20',
            'name' => '20% off Sole Goods',
            'type' => 'percent',
            'value' => 20,
            'min_spend' => 300,
            'max_discount' => 100,
        ])->assertSessionHasNoErrors();

        $voucher = Voucher::where('code', 'SOLE20')->firstOrFail();
        $this->assertSame((int) $seller->id, (int) $voucher->seller_id);
        $this->assertSame((int) $seller->id, (int) $voucher->created_by);
        $this->assertSame('seller', $voucher->funded_by);
        $this->assertTrue($voucher->is_active);

        // VoucherSeller row is automatically accepted.
        $pivot = VoucherSeller::where('voucher_id', $voucher->id)->firstOrFail();
        $this->assertSame(VoucherSeller::ACCEPTED, $pivot->status);

        // Shows in "My shop vouchers" and not in platform invitations.
        $this->get(route('seller.vouchers'))->assertOk()
            ->assertSee('My shop vouchers')
            ->assertSee('SOLE20')
            ->assertSee('Create shop voucher');

        // Seller can pause (toggle) voucher.
        $this->patch(route('seller.vouchers.toggle', $voucher))->assertSessionHasNoErrors();
        $this->assertFalse($voucher->fresh()->is_active);

        // Other seller cannot toggle or delete it.
        $this->actingAs($otherSeller)->patch(route('seller.vouchers.toggle', $voucher))->assertForbidden();
        $this->actingAs($otherSeller)->delete(route('seller.vouchers.destroy', $voucher))->assertForbidden();

        // Original seller deletes it.
        $this->actingAs($seller)->delete(route('seller.vouchers.destroy', $voucher))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }

    public function test_buyer_checkout_with_seller_voucher_applies_only_to_that_seller(): void
    {
        $sellerA = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Bakery Delight']);
        $sellerB = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Coffee Bean']);

        $bread = Product::create(['seller_id' => $sellerA->id, 'name' => 'Sourdough Bread', 'category' => 'storage_bins', 'price' => 500, 'stock' => 10, 'is_active' => true]);
        $coffee = Product::create(['seller_id' => $sellerB->id, 'name' => 'Cold Brew', 'category' => 'storage_bins', 'price' => 300, 'stock' => 10, 'is_active' => true]);

        // Seller A creates a 20% voucher.
        $this->actingAs($sellerA)->post(route('seller.vouchers.store'), [
            'code' => 'BAKERY20',
            'type' => 'percent',
            'value' => 20,
            'min_spend' => 400,
        ])->assertSessionHasNoErrors();
        $voucher = Voucher::where('code', 'BAKERY20')->firstOrFail();

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $buyer->addresses()->create($this->savedAddressData(['is_default' => true]));

        // Cart with items from both sellers: only Seller A's items get the 20% discount (20% of ₱500 = ₱100).
        $this->actingAs($buyer)->post(route('buyer.cart.add', $bread), ['quantity' => 1]);
        $this->post(route('buyer.cart.add', $coffee), ['quantity' => 1]);
        $items = $buyer->cart()->first()->items()->with('product')->get();

        $discounts = app(Vouchers::class)->cartDiscounts($voucher, $items);
        $this->assertSame([(int) $sellerA->id => 100.0], $discounts);

        // Cart with ONLY Seller B's items should be refused with a clear message.
        $this->signInMobile($buyer);
        $coffeeItem = $items->firstWhere('product.id', $coffee->id);
        $response = $this->postJson('/api/mobile/checkout/quote', [
            'cart_item_ids' => [$coffeeItem->id],
            'voucher_code' => 'BAKERY20',
        ]);
        $response->assertUnprocessable();
        $this->assertStringContainsString('only applies to items from', $response->json('errors.voucher_code.0'));
    }
}
