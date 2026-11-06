<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\ShippingRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

/**
 * The buyer picks a logistics partner at checkout, and the shipping fee follows how far the delivery address is
 * from that partner's hub: same city, same province, same region, same island group, or another island group.
 */
class ShippingRatesTest extends TestCase
{
    use PsgcFixtures;
    use RefreshDatabase;

    private const BACOOR = ['region_code' => '040000000', 'province_code' => '042100000', 'city_code' => '042103000'];

    private function hub(array $codes, string $name = 'Express Delivery'): LogisticsProvider
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);

        return LogisticsProvider::create([
            'user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved',
            'city_name' => 'City of Bacoor', 'province_name' => 'Cavite',
        ] + $codes);
    }

    private function to(string $region, ?string $province, string $city): Address
    {
        return new Address(['region_code' => $region, 'province_code' => $province, 'city_municipality_code' => $city]);
    }

    public function test_the_fee_follows_the_distance_from_the_partners_hub(): void
    {
        $bacoor = new LogisticsProvider(self::BACOOR);
        $zones = [
            'same_city' => $this->to('040000000', '042100000', '042103000'), // Bacoor
            'same_province' => $this->to('040000000', '042100000', '042105000'), // another Cavite town
            'same_region' => $this->to('040000000', '043400000', '043404000'), // Laguna
            'same_island_group' => $this->to('130000000', null, '137404000'), // Quezon City, Metro Manila
            'other_island_group' => $this->to('070000000', '072200000', '072217000'), // Cebu City
        ];
        foreach ($zones as $zone => $address) {
            $this->assertSame($zone, ShippingRates::zone($bacoor, $address), $zone);
        }
        $this->assertSame([60.0, 85.0, 110.0, 150.0, 200.0], array_map(fn ($zone) => ShippingRates::fee($zone), array_keys($zones)));

        // Metro Manila has no provinces: another of its cities counts as the same province.
        $makati = new LogisticsProvider(['region_code' => '130000000', 'city_code' => '137602000']);
        $this->assertSame('same_province', ShippingRates::zone($makati, $zones['same_island_group']));

        // Without a partner or located addresses, the flat fee applies.
        $this->assertNull(ShippingRates::zone(new LogisticsProvider, $zones['same_city']));
        $this->assertNull(ShippingRates::zone($bacoor, new Address));
        $this->assertSame(120.0, ShippingRates::fee(null));
    }

    public function test_checkout_offers_each_partner_with_its_fee_to_each_address(): void
    {
        $near = $this->hub(self::BACOOR, 'Cavite Express');
        $far = $this->hub(['region_code' => '070000000', 'province_code' => '072200000', 'city_code' => '072217000'], 'Cebu Couriers');
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'category' => 'storage_bins', 'price' => 450, 'stock' => 4, 'is_active' => true]);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $token = $buyer->createToken('test');
        $item = $this->withToken($token)->postJson('/api/mobile/cart/'.$product->slug, ['quantity' => 1])->assertCreated()->json('added_item_id');
        $address = $buyer->addresses()->create($this->savedAddressData()); // Bacoor, Cavite

        $quote = $this->withToken($token)->postJson('/api/mobile/checkout/quote', ['cart_item_ids' => [$item]])->assertOk()
            ->assertJsonPath('default_logistics_provider_id', $near->id)
            ->assertJsonPath('totals.shipping', 60)
            ->assertJsonPath('totals.shipping_zone', 'same_city')
            ->assertJsonPath('shipping_per_seller', 120);
        $rates = collect($quote->json('logistics_partners'))->keyBy('name');
        $this->assertSame([60, 'Same city or municipality'], [$rates['Cavite Express']['rates'][$address->id]['fee'], $rates['Cavite Express']['rates'][$address->id]['label']]);
        $this->assertSame(200, $rates['Cebu Couriers']['rates'][$address->id]['fee']);
        $this->assertSame('City of Bacoor, Cavite', $rates['Cavite Express']['hub']);

        // The fee and partner the order records come from the same service; a partner that stopped taking parcels is refused.
        $checkout = app(CheckoutService::class);
        $this->assertSame($far->id, $checkout->partnerFor($far->id, $address)->id);
        $this->assertSame(200.0, $checkout->quote(collect(), null, $address, $far)['shipping_per_seller']);
        $this->expectException(ValidationException::class);
        $checkout->partnerFor(999, $address);
    }

    public function test_the_website_checkout_lists_partners_with_their_fees(): void
    {
        $this->hub(self::BACOOR, 'Cavite Express');
        $this->hub(['region_code' => '070000000', 'province_code' => '072200000', 'city_code' => '072217000'], 'Cebu Couriers');
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'category' => 'storage_bins', 'price' => 450, 'stock' => 4, 'is_active' => true]);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $item = $this->withToken($buyer->createToken('test'))->postJson('/api/mobile/cart/'.$product->slug, ['quantity' => 1])->assertCreated()->json('added_item_id');
        $buyer->addresses()->create($this->savedAddressData());

        $this->actingAs($buyer)->withSession(['checkout_cart_item_ids' => [$item]])->get(route('buyer.checkout.show'))->assertOk()
            ->assertSee('Logistics partner')->assertSee('Cavite Express')->assertSee('Cebu Couriers')
            ->assertSee('&#8369;60.00 per seller · Same city or municipality', false)
            ->assertSee('&#8369;200.00 per seller · Another island group', false)
            // The nearest partner is chosen, so the summary starts at its fee.
            ->assertSee('data-summary-total>&#8369;510.00', false)
            ->assertSee('js/checkout-shipping.js', false);
    }

    public function test_orders_from_older_apps_go_to_the_nearest_partner(): void
    {
        $this->hub(['region_code' => '070000000', 'province_code' => '072200000', 'city_code' => '072217000'], 'Cebu Couriers');
        $near = $this->hub(['region_code' => '040000000', 'province_code' => '043400000', 'city_code' => '043404000'], 'Laguna Logistics');
        $address = new Address($this->savedAddressData());

        $partner = app(CheckoutService::class)->partnerFor(null, $address);

        $this->assertSame($near->id, $partner->id);
        $this->assertSame('same_region', ShippingRates::zone($partner, $address));
    }

    public function test_without_partners_the_flat_fee_applies(): void
    {
        $address = new Address($this->savedAddressData());

        $this->assertNull(app(CheckoutService::class)->partnerFor(null, $address));
        $this->assertSame(120.0, app(CheckoutService::class)->quote(collect(), null, $address, null)['shipping_per_seller']);
    }
}
