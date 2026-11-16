<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\User;
use App\Support\LogisticsNetworks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesSignUp;
use Tests\TestCase;

class LogisticsAccountAddressAndPinTest extends TestCase
{
    use CompletesSignUp, RefreshDatabase;

    private function createLogisticsPartner(array $providerAttributes = []): array
    {
        $user = User::factory()->create([
            'role' => 'logistics',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $provider = LogisticsProvider::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Fast Cargo Logistics',
            'slug' => 'fast-cargo',
            'status' => 'approved',
            'approved_at' => now(),
            'contact_phone' => '09171112222',
            'service_area' => 'NCR; Region IV-A (CALABARZON)',
            'service_areas' => ['NCR', 'IV-A'],
            'region_code' => '130000000',
            'region_name' => 'NCR',
            'city_code' => '137404000',
            'city_name' => 'Quezon City',
            'barangay_code' => '137404001',
            'barangay_name' => 'Alicia',
            'street_address' => '55 Edsa Extension',
            'postal_code' => '1105',
            'hub_latitude' => 14.6543210,
            'hub_longitude' => 121.0123450,
        ], $providerAttributes));

        return [$user, $provider];
    }

    public function test_logistics_partner_can_view_account_page_with_map_pin_component(): void
    {
        [$user, $provider] = $this->createLogisticsPartner();

        $response = $this->actingAs($user)->get(route('logistics.account'));

        $response->assertOk();
        $response->assertSee('Exact Hub Location on Map');
        $response->assertSee('data-map-pin', false);
        $response->assertSee('name="hub_latitude"', false);
        $response->assertSee('name="hub_longitude"', false);
        $response->assertSee('14.654321', false);
        $response->assertSee('121.012345', false);
    }

    public function test_logistics_partner_can_update_address_and_pin_coordinates(): void
    {
        [$user, $provider] = $this->createLogisticsPartner();

        $response = $this->actingAs($user)->patch(route('logistics.account.company'), [
            'company_name' => 'Fast Cargo Hub Cavite',
            'contact_phone' => '09179998877',
            'service_area' => 'Region IV-A (CALABARZON)',
            'region_code' => '040000000',
            'province_code' => '042100000',
            'city_code' => '042108000',
            'barangay_code' => '042108001',
            'street_address' => 'Blk 10 Lot 4, General Trias Drive',
            'postal_code' => '4107',
            'hub_latitude' => 14.3850123,
            'hub_longitude' => 120.8812345,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $provider->refresh();
        $this->assertSame('Fast Cargo Hub Cavite', $provider->name);
        $this->assertSame('09179998877', $provider->contact_phone);
        $this->assertSame('Blk 10 Lot 4, General Trias Drive', $provider->street_address);
        $this->assertSame('042108000', $provider->city_code);
        $this->assertSame(14.3850123, (float) $provider->hub_latitude);
        $this->assertSame(120.8812345, (float) $provider->hub_longitude);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        [$user, $provider] = $this->createLogisticsPartner();

        // Latitude outside Philippine bounds (4 to 21.5)
        $response = $this->actingAs($user)->patch(route('logistics.account.company'), [
            'company_name' => 'Fast Cargo Logistics',
            'contact_phone' => '09171112222',
            'hub_latitude' => 55.1234567, // Out of bounds
            'hub_longitude' => 121.0123450,
        ]);

        $response->assertSessionHasErrors('hub_latitude');

        // Longitude outside Philippine bounds (116 to 127.5)
        $responseLng = $this->actingAs($user)->patch(route('logistics.account.company'), [
            'company_name' => 'Fast Cargo Logistics',
            'contact_phone' => '09171112222',
            'hub_latitude' => 14.6543210,
            'hub_longitude' => 10.0123450, // Out of bounds
        ]);

        $responseLng->assertSessionHasErrors('hub_longitude');
    }

    public function test_branch_cannot_update_address_to_same_address_as_main_hub(): void
    {
        // Create parent Zaylo main hub
        $parentOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $zayloParent = LogisticsProvider::create([
            'user_id' => $parentOwner->id,
            'name' => 'Zaylo Express Delivery',
            'slug' => 'zaylo-express',
            'network_brand' => 'zaylo_express',
            'status' => 'approved',
            'approved_at' => now(),
            'street_address' => 'Zaylo Central Hub, Km 18 West Service Road',
            'region_code' => '130000000',
            'city_code' => '137404000',
            'barangay_code' => '137404001',
            'postal_code' => '1105',
        ]);

        [$branchUser, $branchProvider] = $this->createLogisticsPartner([
            'parent_id' => $zayloParent->id,
            'network_brand' => 'zaylo_express',
            'branch_name' => 'Cavite Hub',
        ]);

        // Branch attempts to change address to match main hub
        $response = $this->actingAs($branchUser)->patch(route('logistics.account.company'), [
            'company_name' => 'Zaylo Express Delivery - Cavite Hub',
            'contact_phone' => '09171112222',
            'region_code' => '130000000',
            'city_code' => '137404000',
            'barangay_code' => '137404001',
            'street_address' => 'Zaylo Central Hub, Km 18 West Service Road',
            'postal_code' => '1105',
            'hub_latitude' => 14.6543210,
            'hub_longitude' => 121.0123450,
        ]);

        $response->assertSessionHasErrors('street_address');
    }

    public function test_editing_application_with_pinned_coordinates_saves_on_resubmit(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'rejected']);
        $provider = LogisticsProvider::create([
            'user_id' => $owner->id,
            'name' => 'Old Logistics Hub',
            'slug' => 'old-logistics',
            'status' => 'rejected',
            'rejection_reason' => 'Address not clear',
            'sec_registration_no' => 'CS202611111',
            'tin' => '111-222-333-000',
            'contact_position' => 'Manager',
            'service_areas' => ['NCR'],
            'region_code' => '130000000',
            'city_code' => '137404000',
            'barangay_code' => '137404001',
            'street_address' => 'Unit 1, 99 Quezon Ave',
            'postal_code' => '1100',
            'sec_certificate_path' => 'docs/sec.pdf',
            'bir_2303_path' => 'docs/bir.pdf',
            'business_permit_path' => 'docs/permit.pdf',
            'representative_id_path' => 'docs/id.jpg',
        ]);

        $response = $this->actingAs($owner)->post(route('logistics.application.update'), [
            'company_name' => 'Corrected Logistics Hub',
            'sec_registration_no' => 'CS202611111',
            'tin' => '111-222-333-000',
            'contact_position' => 'Manager',
            'service_areas' => ['NCR'],
            'region_code' => '130000000',
            'city_code' => '137404000',
            'barangay_code' => '137404001',
            'street_address' => 'Unit 88, Sunrise Tower, Quezon Ave',
            'postal_code' => '1100',
            'hub_latitude' => 14.6345678,
            'hub_longitude' => 121.0345678,
        ]);

        $response->assertRedirect(route('logistics.application'));
        $provider->refresh();
        $this->assertSame(14.6345678, (float) $provider->hub_latitude);
        $this->assertSame(121.0345678, (float) $provider->hub_longitude);
        $this->assertSame('pending', $owner->fresh()->status);
    }
}

