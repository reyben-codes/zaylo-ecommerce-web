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

class LogisticsNetworkBranchApplicationTest extends TestCase
{
    use CompletesSignUp, RefreshDatabase;

    private function createParentNetworkProviders(): array
    {
        $zayloOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $zaylo = LogisticsProvider::create([
            'user_id' => $zayloOwner->id,
            'name' => 'Zaylo Express Delivery',
            'slug' => 'zaylo-express',
            'network_brand' => 'zaylo_express',
            'waybill_layout' => 'zaylo_express',
            'status' => 'approved',
            'approved_at' => now(),
            'contact_phone' => '09170000001',
            'street_address' => 'Zaylo Central Hub, Km 18 West Service Road',
            'region_code' => '130000000',
            'city_code' => '137404000',
            'barangay_code' => '137404001',
            'postal_code' => '1105',
        ]);

        $jntOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $jnt = LogisticsProvider::create([
            'user_id' => $jntOwner->id,
            'name' => 'J&T Express',
            'slug' => 'jnt-express',
            'network_brand' => 'jnt_express',
            'waybill_layout' => 'jnt_express',
            'status' => 'approved',
            'approved_at' => now(),
            'contact_phone' => '09170000002',
            'street_address' => 'Purok 1',
            'region_code' => '040000000',
            'city_code' => '043415000',
            'barangay_code' => '043415004',
            'postal_code' => '4007',
        ]);

        $flashOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $flash = LogisticsProvider::create([
            'user_id' => $flashOwner->id,
            'name' => 'Flash Express',
            'slug' => 'flash-express',
            'network_brand' => 'flash_express',
            'waybill_layout' => 'flash_express',
            'status' => 'approved',
            'approved_at' => now(),
            'contact_phone' => '09170000003',
            'street_address' => 'Rizal Street, Highway',
            'region_code' => '040000000',
            'city_code' => '043422000',
            'barangay_code' => '043422005',
            'postal_code' => '4010',
        ]);

        return [$zaylo, $jnt, $flash];
    }

    public function test_logistics_role_without_network_shows_network_choice_panel(): void
    {
        $response = $this->get(route('logistics.apply'));

        $response->assertOk();
        $response->assertSee('LOGISTICS PARTNER NETWORK');
        $response->assertSee('Choose Logistics Partner');
        $response->assertSee('Zaylo Express Delivery');
        $response->assertSee('J&T Express');
        $response->assertSee('Flash Express');
        $response->assertSee('Regional Branch Hub');
    }

    public function test_convenience_logistics_apply_route_redirects(): void
    {
        $this->get('/logistics/apply?network=jnt_express')
            ->assertRedirect(route('register', ['role' => 'logistics', 'network' => 'jnt_express']));
    }

    public function test_choosing_network_shows_branch_application_form(): void
    {
        $response = $this->get(route('register', ['role' => 'logistics', 'network' => 'zaylo_express']));

        $response->assertOk();
        $response->assertSee('Apply as a Zaylo Express Branch');
        $response->assertSee('Zaylo Express Delivery');
        $response->assertSee('Branch / Hub Name');
        $response->assertSee('Registered Hub Identity');
        $response->assertSee('Change Partner');
    }

    public function test_branch_registration_saves_parent_id_and_formatted_identity(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$zayloParent] = $this->createParentNetworkProviders();

        $user = $this->signUp([
            'role' => 'logistics',
            'network' => 'zaylo_express',
            'branch_name' => 'Cavite Central Hub',
            'sec_registration_no' => 'CS202612345',
            'tin' => '123-456-789-000',
            'contact_position' => 'Branch Manager',
            'first_name' => 'Ricardo',
            'last_name' => 'Dalisay',
            'email' => 'ricardo.dalisay@zaylo-hub.ph',
            'phone' => '09179998888',
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
            'terms' => '1',
            'region_code' => '040000000',
            'province_code' => '042100000',
            'city_code' => '042108000',
            'barangay_code' => '042108001',
            'street_address' => 'Blk 5 Lot 12 Governor Drive',
            'postal_code' => '4114',
            'service_areas' => ['IV-A', 'NCR'],
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 500, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->create('id.jpg', 500, 'image/jpeg'),
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'ricardo.dalisay@zaylo-hub.ph',
        ]);
        $this->assertSame('logistics', $user->role);

        // Check provider created linked to parent
        $provider = $user->logisticsProvider;
        $this->assertNotNull($provider);
        $this->assertSame('Zaylo Express Delivery - Cavite Central Hub', $provider->name);
        $this->assertSame('Cavite Central Hub', $provider->branch_name);
        $this->assertSame('zaylo_express', $provider->network_brand);
        $this->assertSame($zayloParent->id, $provider->parent_id);
        $this->assertSame('zaylo_express', $provider->waybill_layout);
        $this->assertTrue($provider->isBranch());
        $this->assertSame($zayloParent->id, $provider->parent->id);
    }

    public function test_jnt_and_flash_branch_registration(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$zaylo, $jntParent, $flashParent] = $this->createParentNetworkProviders();

        // Register J&T branch
        $jntUser = $this->signUp([
            'role' => 'logistics',
            'network' => 'jnt_express',
            'branch_name' => 'Quezon City North Hub',
            'sec_registration_no' => 'CS202688888',
            'tin' => '987-654-321-000',
            'contact_position' => 'Hub Supervisor',
            'first_name' => 'Cardo',
            'last_name' => 'Santos',
            'email' => 'cardo.jnt@branch.ph',
            'phone' => '09181112233',
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
            'terms' => '1',
            'region_code' => '130000000',
            'city_code' => '137404000',
            'barangay_code' => '137404001',
            'street_address' => '120 Commonwealth Ave',
            'postal_code' => '1121',
            'service_areas' => ['NCR'],
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 500, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->create('id.jpg', 500, 'image/jpeg'),
        ]);

        $jntBranch = $jntUser->logisticsProvider;
        $this->assertNotNull($jntBranch);
        $this->assertSame('J&T Express - Quezon City North Hub', $jntBranch->name);
        $this->assertSame('jnt_express', $jntBranch->network_brand);
        $this->assertSame($jntParent->id, $jntBranch->parent_id);
        $this->assertSame('jnt_express', $jntBranch->waybill_layout);
    }

    public function test_registering_hub_cannot_pick_same_address_as_main_hub(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$zayloParent] = $this->createParentNetworkProviders();

        // Submitting with the exact same address as the Zaylo main hub
        $response = $this->post(route('register'), [
            'role' => 'logistics',
            'network' => 'zaylo_express',
            'branch_name' => 'Duplicate Hub Address',
            'sec_registration_no' => 'CS202699999',
            'tin' => '111-222-333-000',
            'contact_position' => 'Hub Manager',
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'email' => 'juan.cruz@duplicate-hub.ph',
            'phone' => '09171239999',
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
            'terms' => '1',
            'region_code' => '130000000',
            'city_code' => '137404000',
            'barangay_code' => '137404001',
            'street_address' => 'Zaylo Central Hub, Km 18 West Service Road',
            'postal_code' => '1105',
            'service_areas' => ['NCR'],
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 500, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->create('id.jpg', 500, 'image/jpeg'),
        ]);

        $response->assertSessionHasErrors('street_address');
        $this->assertDatabaseMissing('users', ['email' => 'juan.cruz@duplicate-hub.ph']);
    }

    public function test_jnt_hub_cannot_pick_same_address_as_jnt_main_hub(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$zaylo, $jntParent] = $this->createParentNetworkProviders();

        // Submitting with the same address as J&T main hub (Purok 1, Baanan, Magdalena)
        $response = $this->post(route('register'), [
            'role' => 'logistics',
            'network' => 'jnt_express',
            'branch_name' => 'Duplicate J&T Hub',
            'sec_registration_no' => 'CS202677777',
            'tin' => '333-444-555-000',
            'contact_position' => 'Hub Manager',
            'first_name' => 'Mario',
            'last_name' => 'Reyes',
            'email' => 'mario.reyes@duplicate-jnt.ph',
            'phone' => '09175556677',
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
            'terms' => '1',
            'region_code' => '040000000',
            'province_code' => '043400000',
            'city_code' => '043415000',
            'barangay_code' => '043415004',
            'street_address' => 'Purok 1',
            'postal_code' => '4007',
            'service_areas' => ['IV-A'],
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 500, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir.pdf', 500, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->create('id.jpg', 500, 'image/jpeg'),
        ]);

        $response->assertSessionHasErrors('street_address');
        $this->assertDatabaseMissing('users', ['email' => 'mario.reyes@duplicate-jnt.ph']);
    }
}
