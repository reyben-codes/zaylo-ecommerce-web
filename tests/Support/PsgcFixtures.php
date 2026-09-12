<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

trait PsgcFixtures
{
    protected function fakeLocations(): void
    {
        Http::preventStrayRequests();
        $base = config('locations.base_url').'/';
        Http::fake([
            $base.'regions/' => Http::response([
                ['code' => '040000000', 'name' => 'CALABARZON'],
                ['code' => '130000000', 'name' => 'National Capital Region'],
            ]),
            $base.'regions/040000000/provinces/' => Http::response([
                ['code' => '042100000', 'name' => 'Cavite', 'regionCode' => '040000000'],
            ]),
            $base.'regions/130000000/provinces/' => Http::response([]),
            $base.'regions/040000000/cities-municipalities/' => Http::response([
                ['code' => '042103000', 'name' => 'City of Bacoor', 'provinceCode' => '042100000', 'regionCode' => '040000000'],
            ]),
            $base.'provinces/042100000/cities-municipalities/' => Http::response([
                ['code' => '042103000', 'name' => 'City of Bacoor', 'provinceCode' => '042100000', 'regionCode' => '040000000'],
            ]),
            $base.'regions/130000000/cities-municipalities/' => Http::response([
                ['code' => '137404000', 'name' => 'Quezon City', 'provinceCode' => false, 'regionCode' => '130000000'],
            ]),
            $base.'cities-municipalities/042103000/barangays/' => Http::response([
                ['code' => '042103001', 'name' => 'Alima', 'cityCode' => '042103000'],
            ]),
            $base.'cities-municipalities/137404000/barangays/' => Http::response([
                ['code' => '137404001', 'name' => 'Alicia', 'cityCode' => '137404000'],
            ]),
        ]);
    }

    protected function addressInput(array $overrides = []): array
    {
        return array_replace([
            'recipient_name' => 'Test Buyer', 'phone' => '09170000000',
            'region_code' => '040000000', 'province_code' => '042100000',
            'city_municipality_code' => '042103000', 'barangay_code' => '042103001',
            'line1' => '1 Test Street', 'postal_code' => '4102', 'label' => 'Home',
            'is_default' => false,
        ], $overrides);
    }

    protected function savedAddressData(array $overrides = []): array
    {
        return array_replace($this->addressInput(), [
            'region_name' => 'CALABARZON', 'province' => 'Cavite', 'city' => 'City of Bacoor', 'barangay' => 'Alima',
        ], $overrides);
    }
}
