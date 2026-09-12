<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use PsgcFixtures;

    public function test_location_endpoints_proxy_and_cache_the_selected_parents(): void
    {
        $this->fakeLocations();
        foreach ([
            '/api/locations/regions' => '040000000',
            '/api/locations/regions/040000000/provinces' => '042100000',
            '/api/locations/provinces/042100000/cities-municipalities' => '042103000',
            '/api/locations/cities-municipalities/042103000/barangays' => '042103001',
            '/api/locations/regions/130000000/cities-municipalities' => '137404000',
        ] as $url => $code) {
            $this->getJson($url)->assertOk()->assertJsonPath('0.code', $code);
            $this->getJson($url)->assertOk()->assertJsonPath('0.code', $code);
        }
        Http::assertSentCount(5);
        $this->travel(86401)->seconds();
        $this->getJson('/api/locations/regions')->assertOk();
        Http::assertSentCount(6);
    }

    public function test_ncr_has_no_provinces_and_direct_route_excludes_province_bound_cities(): void
    {
        $this->fakeLocations();
        $this->getJson('/api/locations/regions/130000000/provinces')->assertOk()->assertExactJson([]);
        $this->getJson('/api/locations/regions/040000000/cities-municipalities')->assertOk()->assertExactJson([]);
    }

    public function test_bad_location_codes_never_reach_upstream(): void
    {
        Http::fake();
        $this->getJson('/api/locations/regions/bad/provinces')->assertNotFound();
        $this->getJson('/api/locations/provinces/123/cities-municipalities')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_failures_and_malformed_responses_are_not_cached(): void
    {
        Http::fake(['*' => Http::sequence()->push([], 503)->push(['error' => 'bad response'])->push([['code' => '040000000', 'name' => 'CALABARZON']])]);
        $this->getJson('/api/locations/regions')->assertStatus(503)->assertJsonPath('message', 'Location service is temporarily unavailable. Please try again.');
        $this->getJson('/api/locations/regions')->assertStatus(503);
        $this->getJson('/api/locations/regions')->assertOk();
        Http::assertSentCount(3);
    }

    public function test_connection_failures_return_a_retryable_response(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->getJson('/api/locations/regions')->assertStatus(503)->assertHeader('Retry-After', '30');
    }
}
