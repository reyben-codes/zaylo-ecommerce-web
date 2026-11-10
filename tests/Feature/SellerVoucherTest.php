<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Models\VoucherSeller;
use App\Notifications\VoucherInvitation;
use App\Services\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PsgcFixtures;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Discount vouchers are offered to every shop by notification and email; shops that join have the discount on their
 * products and pay it, shops that decline are left out. Global free shipping needs no opt-in (ZAYLO pays it).
 */
class SellerVoucherTest extends TestCase
{
    use LegacyOrderTables, PsgcFixtures, RefreshDatabase, SignsInMobile;

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
        $this->get(route('buyer.checkout.show'))->assertOk()
            ->assertSee('Available vouchers for your order')
            ->assertSee('PAYDAY10');
        $this->get(route('buyer.checkout.show', ['voucher' => 'payday10']))->assertOk()
            ->assertSee('PAYDAY10 applied: 10% off')->assertSee('Voucher PAYDAY10')->assertSee('data-voucher-discount="100"', false);

        // The app quote has the same discount and includes available_vouchers.
        $this->signInMobile($buyer);
        $this->postJson('/api/mobile/checkout/quote', ['cart_item_ids' => $items->pluck('id')->all(), 'voucher_code' => 'PAYDAY10'])->assertOk()
            ->assertJsonPath('totals.voucher_discount', 100)
            ->assertJsonPath('voucher_label', '10% off, up to ₱150.00, min. spend ₱500.00')
            ->assertJsonPath('available_vouchers.0.code', 'PAYDAY10');
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
        $this->assertDatabaseMissing('voucher_claims', ['voucher_id' => $voucher->id, 'user_id' => $buyer->id]);

        // An eligible quote claims the voucher, but a used claim cannot be applied again.
        $this->postJson('/api/mobile/checkout/quote', [
            'cart_item_ids' => $items->pluck('id')->all(),
            'voucher_code' => 'BAKERY20',
        ])->assertOk();
        $claim = VoucherClaim::where('voucher_id', $voucher->id)->where('user_id', $buyer->id)->firstOrFail();
        $claim->update(['used_at' => now()]);
        $this->postJson('/api/mobile/checkout/quote', [
            'cart_item_ids' => $items->pluck('id')->all(),
        ])->assertOk()->assertJsonPath('available_vouchers.0.can_apply', false)
            ->assertJsonPath('available_vouchers.0.reason_cannot_apply', 'Already used');
        $this->postJson('/api/mobile/checkout/quote', [
            'cart_item_ids' => $items->pluck('id')->all(),
            'voucher_code' => 'BAKERY20',
        ])->assertUnprocessable()->assertJsonValidationErrors('voucher_code');
    }

    public function test_seller_voucher_preview_is_rendered(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Pottery Barn']);

        $this->actingAs($seller)->get(route('seller.vouchers'))
            ->assertOk()
            ->assertSee('seller-voucher-creator-grid')
            ->assertSee('Buyer preview')
            ->assertSee('seller-voucher-preview-card')
            ->assertSee('seller-voucher-preview.js');
    }

    public function test_checkout_uses_a_modal_for_multiple_claimable_shop_vouchers(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $buyer->addresses()->create($this->savedAddressData(['is_default' => true]));
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Shop item', 'category' => 'storage_bins', 'price' => 500, 'stock' => 10, 'is_active' => true]);
        $createVoucher = function (User $owner, string $code) {
            $this->actingAs($owner)->post(route('seller.vouchers.store'), [
                'code' => $code, 'type' => 'fixed', 'value' => 50,
            ])->assertSessionHasNoErrors();
        };
        $createVoucher($seller, 'SHOPFIRST');
        $createVoucher($otherSeller, 'UNRELATEDSHOP');
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => 1]);
        $this->get(route('buyer.checkout.show'))->assertOk()->assertSee('SHOPFIRST')
            ->assertDontSee('UNRELATEDSHOP')->assertDontSee('data-vouchers-open', false);

        $createVoucher($seller, 'SHOPSECOND');
        $this->actingAs($buyer)->get(route('buyer.checkout.show'))->assertOk()
            ->assertSee('data-vouchers-open', false)->assertSee('id="checkout-vouchers-dialog"', false)
            ->assertSee('SHOPFIRST')->assertSee('SHOPSECOND')->assertDontSee('UNRELATEDSHOP');

        $this->post(route('buyer.vouchers.claim', Voucher::where('code', 'SHOPFIRST')->firstOrFail()))
            ->assertSessionHasNoErrors();
        $this->get(route('buyer.checkout.show'))->assertOk()->assertSee('SHOPSECOND')
            ->assertSee('Claimed')->assertDontSee('data-vouchers-open', false);
    }

    public function test_buyer_can_claim_voucher_and_usage_limit_prevents_other_claims(): void
    {
        $this->useLegacyOrderTables();
        \Illuminate\Support\Facades\Schema::table('payments', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('currency')->default('PHP');
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('payload')->nullable();
        });
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Fashion Boutique']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Silk Dress', 'category' => 'storage_bins', 'price' => 1200, 'stock' => 10, 'is_active' => true]);

        // Seller creates a voucher with usage_limit = 1 (only 1 claim/use allowed).
        $this->actingAs($seller)->post(route('seller.vouchers.store'), [
            'code' => 'LIMITEDONE',
            'name' => 'VIP One Customer Only',
            'type' => 'fixed',
            'value' => 100,
            'usage_limit' => 1,
            'min_spend' => 500,
        ])->assertSessionHasNoErrors();
        $voucher = Voucher::where('code', 'LIMITEDONE')->firstOrFail();

        $buyerA = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $buyerA->addresses()->create($this->savedAddressData(['is_default' => true]));
        $buyerB = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $buyerB->addresses()->create($this->savedAddressData(['is_default' => true]));

        // Buyer A adds item to cart and sees the voucher with Claim button.
        $this->actingAs($buyerA)->post(route('buyer.cart.add', $product), ['quantity' => 1]);
        $buyerAItem = $buyerA->cart()->first()->items()->first();
        $this->post(route('buyer.cart.selection'), ['action' => 'checkout', 'cart_item_ids' => [$buyerAItem->id]]);

        $responseA = $this->get(route('buyer.checkout.show'));
        $responseA->assertOk()
            ->assertSee('LIMITEDONE')
            ->assertSee('Claim');

        // Buyer A claims the voucher.
        $this->post(route('buyer.vouchers.claim', $voucher))->assertSessionHasNoErrors();
        $this->post(route('buyer.vouchers.claim', $voucher))->assertSessionHasNoErrors();
        $this->assertSame(1, $voucher->claims()->count());
        $this->assertDatabaseHas('voucher_claims', [
            'voucher_id' => $voucher->id,
            'user_id' => $buyerA->id,
            'used_at' => null,
        ]);

        // Now Buyer A checkout shows it as Claimed.
        $this->get(route('buyer.checkout.show'))->assertOk()
            ->assertSee('Claimed');

        // Buyer B adds item to cart and views checkout: the voucher is Fully Claimed!
        $this->actingAs($buyerB)->post(route('buyer.cart.add', $product), ['quantity' => 1]);
        $buyerBItem = $buyerB->cart()->first()->items()->first();
        $this->post(route('buyer.cart.selection'), ['action' => 'checkout', 'cart_item_ids' => [$buyerBItem->id]]);

        $responseB = $this->get(route('buyer.checkout.show'));
        $responseB->assertOk()
            ->assertSee('Fully claimed');

        // If Buyer B attempts to claim the voucher, they are rejected.
        $this->post(route('buyer.vouchers.claim', $voucher))->assertSessionHasErrors('voucher');

        // If Buyer B attempts to apply the code at checkout, it is rejected.
        $this->get(route('buyer.checkout.show', ['voucher' => 'LIMITEDONE']))
            ->assertSessionHasErrors('voucher_code');

        // Mobile API: Buyer B trying to claim via mobile endpoint receives 422.
        $this->signInMobile($buyerB);
        $this->postJson('/api/mobile/vouchers/LIMITEDONE/claim')
            ->assertUnprocessable();

        // Buyer A completes checkout with the voucher.
        $this->withoutToken()->actingAs($buyerA, 'web');
        $addressA = $buyerA->addresses()->first();
        $this->post(route('buyer.checkout'), [
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'cart_item_ids' => [$buyerAItem->id],
            'quoted_unit_prices' => [$buyerAItem->id => '1200.00'],
            'address_id' => $addressA->id,
            'payment_method' => 'cod',
            'voucher_code' => 'LIMITEDONE',
        ])->assertSessionHasNoErrors()->assertRedirect(route('buyer.orders'));

        // Voucher claim is marked used, used_count is incremented.
        $this->assertDatabaseHas('voucher_claims', [
            'voucher_id' => $voucher->id,
            'user_id' => $buyerA->id,
        ]);
        $claim = VoucherClaim::where('voucher_id', $voucher->id)->where('user_id', $buyerA->id)->first();
        $this->assertNotNull($claim->used_at);
        $this->assertSame(1, $voucher->fresh()->used_count);
    }
}
