<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\User;
use App\Services\ShippingRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PsgcFixtures;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class ShippingRatesTest extends TestCase
{
    use PsgcFixtures, RefreshDatabase, SignsInMobile;

    public function test_shipping_zones_describe_routing_but_the_buyer_pays_the_standard_fee(): void
    {
        $hub = new LogisticsProvider(['region_code' => '040000000', 'province_code' => '042100000', 'city_code' => '042103000']);
        $zones = [
            'same_city' => ['040000000', '042100000', '042103000'],
            'same_province' => ['040000000', '042100000', '042105000'],
            'same_region' => ['040000000', '043400000', '043404000'],
            'same_island_group' => ['130000000', null, '137404000'],
            'other_island_group' => ['070000000', '072200000', '072217000'],
        ];
        foreach ($zones as $zone => [$region, $province, $city]) {
            $address = new Address(['region_code' => $region, 'province_code' => $province, 'city_municipality_code' => $city]);
            $this->assertSame($zone, ShippingRates::zone($hub, $address));
            $this->assertSame(120.0, ShippingRates::fee($zone));
        }
        $this->assertNull(ShippingRates::zone(null, new Address));
        $this->assertSame(120.0, ShippingRates::fee(null));
    }

    public function test_web_and_mobile_checkout_leave_partner_selection_to_the_seller(): void
    {
        foreach (['Cavite Express', 'Cebu Couriers'] as $name) {
            LogisticsProvider::create([
                'user_id' => User::factory()->create(['role' => 'logistics', 'status' => 'active'])->id,
                'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved',
            ]);
        }
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Rattan Basket', 'category' => 'storage_bins', 'price' => 450, 'stock' => 4, 'is_active' => true]);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $item = $this->signInMobile($buyer)->postJson('/api/mobile/cart/'.$product->slug, ['quantity' => 1])->assertCreated()->json('added_item_id');
        $buyer->addresses()->create($this->savedAddressData());

        $this->postJson('/api/mobile/checkout/quote', ['cart_item_ids' => [$item]])->assertOk()
            ->assertJsonCount(0, 'logistics_partners')->assertJsonCount(0, 'couriers')
            ->assertJsonPath('default_logistics_provider_id', null)
            ->assertJsonPath('logistics_selected_by', 'seller')
            ->assertJsonPath('totals.shipping', 120)->assertJsonPath('totals.total', 570);

        $this->withoutToken()->actingAs($buyer, 'web')->withSession(['checkout_cart_item_ids' => [$item]])
            ->get(route('buyer.checkout.show'))->assertOk()
            ->assertSee('Each seller chooses an approved ZAYLO logistics partner')
            ->assertDontSee('name="logistics_provider_id"', false)->assertDontSee('name="courier_choice"', false)
            ->assertDontSee('Cavite Express')->assertDontSee('Cebu Couriers')
            ->assertSee('data-summary-total>&#8369;570.00', false);
    }
}
