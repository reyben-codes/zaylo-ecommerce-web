<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderLegCommission;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\LogisticsPayouts;
use App\Services\RiderLegEarnings;
use App\Services\RiderWallet;
use App\Services\Settlement;
use App\Services\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeliveryFinancialRulesTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Support\PsgcFixtures;

    public static function shippingSponsors(): array
    {
        return [['seller'], ['admin']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shippingSponsors')]
    public function test_checkout_saves_the_shipping_voucher_creators_responsibility(string $sponsor): void
    {
        Notification::fake();
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Sponsored shipping item', 'category' => 'storage_bins', 'price' => 1000, 'stock' => 10, 'is_active' => true]);
        $service = app(Vouchers::class);
        $voucher = $sponsor === 'seller'
            ? $service->createForSeller(['code' => 'SPONSOR', 'type' => 'free_shipping'], $product->seller_id, $seller)
            : $service->create(['code' => 'SPONSOR', 'type' => 'free_shipping', 'created_by' => $admin->id, 'seller_id' => $product->seller_id]);
        $item = app(CartService::class)->add($buyer, $product, 1);
        $address = $buyer->addresses()->create($this->savedAddressData());
        $order = app(CheckoutService::class)->place($buyer, [$item->id], [
            'address_id' => $address->id, 'payment_method' => 'cod', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], $voucher->code)->first();
        $split = $order->sellerOrders()->firstOrFail()->settlement_breakdown;
        $this->assertEquals(0, $split['shipping_paid_by_buyer']);
        $this->assertEquals(120, $split['shipping']);
        $this->assertEquals(120, $split[$sponsor === 'seller' ? 'free_shipping_by_seller' : 'free_shipping_by_zaylo']);
        $this->assertEquals($sponsor === 'seller' ? $seller->id : $admin->id, $split['shipping_voucher_created_by']);
        $this->assertEquals($sponsor === 'seller' ? 680 : 800, $split['seller_earnings']);
        $this->assertEquals($sponsor === 'seller' ? 100 : -20, $split['admin_earnings']);
    }

    public function test_percentage_discount_uses_product_price_and_recalculates_for_quantity(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Fifty peso item', 'category' => 'storage_bins', 'price' => 50, 'stock' => 10, 'is_active' => true]);
        $voucher = app(Vouchers::class)->createForSeller(['code' => 'HALFPRICE', 'type' => 'percent', 'value' => 50], $product->seller_id, $seller);
        $item = app(CartService::class)->add($buyer, $product, 1);
        $checkout = app(CheckoutService::class);

        $quote = $checkout->quote(collect([$item]), $voucher);
        $this->assertSame(25.0, $quote['voucher_discount']);
        $this->assertSame(25.0 + $quote['shipping'], $quote['total']);
        $item->quantity = 3;
        $this->assertSame(75.0, $checkout->quote(collect([$item]), $voucher)['voucher_discount']);
        $voucher->max_discount = 30;
        $this->assertSame(30.0, $checkout->quote(collect([$item]), $voucher)['voucher_discount']);
        $this->assertSame(0.0, app(Vouchers::class)->discountFor($voucher, $product->seller_id + 1, 50));
    }

    public function test_a_seller_can_create_free_shipping_funded_by_their_own_shop(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->post(route('seller.vouchers.store'), [
            'code' => 'SELLERSHIPS', 'type' => 'free_shipping', 'value' => 999,
            'funded_by' => 'zaylo', 'created_by' => 999,
        ])->assertSessionHasNoErrors();
        $voucher = Voucher::where('code', 'SELLERSHIPS')->firstOrFail();
        $this->assertSame('seller', $voucher->funded_by);
        $this->assertEquals($seller->id, $voucher->created_by);
        $this->assertEquals($seller->sellerShop()->id, $voucher->seller_id);
        $this->assertNull($voucher->value);
        $this->assertNull($voucher->max_discount);

        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'code' => 'ADMINSHIPS', 'type' => 'free_shipping',
            'target_type' => 'specific', 'target_seller_id' => $voucher->seller_id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('zaylo', Voucher::where('code', 'ADMINSHIPS')->firstOrFail()->funded_by);
    }

    public function test_shipping_covers_both_legs_and_reports_them_separately_from_shipping(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $courier = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Financial Hub', 'slug' => 'financial-hub', 'status' => 'approved']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'SPLIT-LEGS', 'total_minor' => 100000, 'payment_method' => 'cod', 'shipping_address' => ['city' => 'Manila']]);
        // Admin's free-shipping voucher pays the full fee; buyer shipping stays zero.
        $split = Settlement::deliveryBreakdown(1000, 0, 120);
        $sellerOrder = $order->sellerOrders()->create(['seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 100000, 'shipping_fee_minor' => 0, 'commission_minor' => 10000, 'status' => 'delivered', 'settlement_breakdown' => $split]);
        $quote = RiderLegEarnings::calculate(2, 5, 1, 120, ['base_per_leg' => 10, 'per_km' => 2, 'per_kg' => 1, 'shipping_budget_percent' => 80]);
        $shipment = $sellerOrder->shipment()->create(['logistics_provider_id' => $provider->id, 'tracking_code' => 'SPLIT-LEGS', 'status' => 'delivered', 'rider_fee_quote' => $quote]);
        foreach (['pickup' => $courier, 'delivery' => $rider] as $leg => $person) {
            RiderLegCommission::create(['shipment_id' => $shipment->id, 'user_id' => $person->id, 'logistics_provider_id' => $provider->id, 'leg' => $leg, 'amount' => $quote[$leg.'_amount'], 'distance_km' => $quote[$leg.'_km'], 'weight_kg' => 1]);
        }
        $settlement = app(Settlement::class)->forShipment($shipment);
        $this->assertEquals(120 - $quote['total'], $settlement['logistics_shipping_earnings']);
        $this->assertEquals($quote['total'], $settlement['pickup_rider_earnings'] + $settlement['delivery_rider_earnings']);
        $this->assertEquals(1000, $settlement['seller_earnings'] + $settlement['admin_earnings'] + $settlement['logistics_earnings'] + $settlement['rider_earnings']);
        $summary = app(LogisticsPayouts::class)->summary(app(LogisticsPayouts::class)->rows($provider));
        $this->assertEquals($quote['pickup_amount'], $summary['first_mile']);
        $this->assertEquals($quote['delivery_amount'], $summary['last_mile']);
        $this->assertEquals(120, $summary['first_mile'] + $summary['last_mile'] + $summary['shipping_remaining']);
        $wallet = app(RiderWallet::class);
        $this->assertEquals($quote['pickup_amount'], $wallet->summary($courier)['first_mile_earned']);
        $this->assertEquals(0, $wallet->summary($courier)['last_mile_earned']);
        $this->assertEquals($quote['delivery_amount'], $wallet->summary($rider)['last_mile_earned']);
        $this->actingAs($owner)->get(route('logistics.earnings'))->assertOk()->assertSee('First-mile courier earnings')->assertSee('Last-mile rider earnings');

        // A seller-created shipping voucher funds the same legs from seller proceeds.
        $sellerFunded = Settlement::deliveryBreakdown(1000, 0, 0, 120);
        $this->assertEquals(680, $sellerFunded['seller_earnings']);
        $this->assertEquals(0, $sellerFunded['free_shipping_by_zaylo']);
        $this->assertEquals(120, $sellerFunded['free_shipping_by_seller']);
    }
}
