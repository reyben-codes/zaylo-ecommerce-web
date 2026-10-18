<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Http\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesSignUp;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

/** Logistics sign-up, admin review with documents, rejection with a reason, and resubmission (black-box TC-LOG cases). */
class LogisticsApplicationTest extends TestCase
{
    use CompletesSignUp, PsgcFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Several sign-up attempts per test; the 6-per-minute limit is covered elsewhere.
        $this->withoutMiddleware(ThrottleRequests::class);
        Notification::fake();
        Storage::fake('local');
        $this->fakeLocations();
    }

    public function test_partner_signs_up_with_business_details_address_regions_and_documents(): void
    {
        $this->get(route('register', ['role' => 'logistics']))->assertOk()
            ->assertSee('name="sec_registration_no"', false)->assertSee('name="tin"', false)->assertSee('name="contact_position"', false)
            ->assertSee('name="service_areas[]"', false)->assertSee('name="region_code"', false)->assertSee('name="business_permit"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->post(route('register'), $this->signupData(['sec_registration_no' => 'cs 202012345']))->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('logistics_providers', 0);
        $this->post(route('verification.verify'), ['otp' => $this->signUpCode('operations@bilishatid.example.com')])
            ->assertRedirect(route('logistics.application'));

        $owner = User::where('email', 'operations@bilishatid.example.com')->firstOrFail();
        $provider = $owner->logisticsProvider;
        $this->assertSame('pending', $owner->status);
        $this->assertSame('CS202012345', $provider->sec_registration_no);
        $this->assertSame('234-567-890-000', $provider->tin);
        $this->assertSame('Operations Manager', $provider->contact_position);
        $this->assertSame(['NCR', 'IV-A'], $provider->service_areas);
        $this->assertSame('NCR; Region IV-A (CALABARZON)', $provider->service_area);
        $this->assertSame('Quezon City', $provider->city_name);
        $this->assertSame('1603', $provider->postal_code);
        foreach (array_keys(LogisticsProvider::DOCUMENTS) as $field) {
            $this->assertStringStartsWith("logistics-applications/{$owner->id}/", $provider->{$field.'_path'});
            Storage::disk('local')->assertExists($provider->{$field.'_path'});
        }

        // Under review: the application page explains it; the workspace stays closed.
        $this->get(route('logistics.application'))->assertOk()->assertSee('Your application is under review');
        $this->get(route('logistics.riders'))->assertRedirect(route('logistics.application'));
    }

    public function test_business_fields_are_checked(): void
    {
        $attempt = fn (array $changes) => $this->from(route('register', ['role' => 'logistics']))->post(route('register'), $this->signupData($changes));

        foreach (['CS20201234', '202012345CS', 'CS2020-12345', '12345678901'] as $sec) {
            $attempt(['sec_registration_no' => $sec])->assertSessionHasErrors(['sec_registration_no' => 'Enter the SEC registration number as 2 letters followed by 9 digits, for example CS202012345.']);
        }
        foreach (['123-456-789-00', '123-456-789-0001', '123-456-78A-000'] as $tin) {
            $attempt(['tin' => $tin])->assertSessionHasErrors(['tin' => 'Enter the TIN as 000-000-000-000 (12 digits).']);
        }
        foreach (['160', '16030', '16A3'] as $postal) {
            $attempt(['postal_code' => $postal])->assertSessionHasErrors(['postal_code' => 'Enter a 4-digit postal code.']);
        }
        $attempt(['service_areas' => []])->assertSessionHasErrors(['service_areas' => 'Choose at least one region you deliver to.']);
        $attempt(['contact_position' => ''])->assertSessionHasErrors('contact_position');
        $attempt(['business_permit' => null])->assertSessionHasErrors(['business_permit' => "Upload your Mayor's / Business Permit."]);
        $attempt(['business_permit' => UploadedFile::fake()->create('price_list.docx', 320)])
            ->assertSessionHasErrors(['business_permit' => "Mayor's / Business Permit: upload a JPG, PNG or PDF file."]);
        $attempt(['business_permit' => UploadedFile::fake()->create('size_5.1MB.jpg', 5222, 'image/jpeg')])
            ->assertSessionHasErrors(['business_permit' => "Mayor's / Business Permit: the file must be 5 MB or smaller."]);
        $this->assertDatabaseCount('pending_registrations', 0);

        $attempt(['business_permit' => UploadedFile::fake()->create('size_5.0MB.jpg', 5120, 'image/jpeg')])->assertSessionHasNoErrors();
    }

    public function test_company_name_sec_number_and_tin_must_be_unique(): void
    {
        $this->signUp($this->signupData());
        $other = fn (array $changes) => $this->from(route('register', ['role' => 'logistics']))->post(route('register'), $this->signupData($changes + [
            'email' => 'admin@agilacargo.example.com', 'phone' => '09396667890', 'company_name' => 'Agila Cargo Movers Corp.',
            'sec_registration_no' => 'CS201998765', 'tin' => '567-890-123-000',
        ]));

        $other(['company_name' => 'bilis hatid express inc.'])->assertSessionHasErrors(['company_name' => 'This company name is already registered.']);
        $other(['sec_registration_no' => 'CS202012345'])->assertSessionHasErrors(['sec_registration_no' => 'This SEC registration number is already registered.']);
        $other(['tin' => '234-567-890-000'])->assertSessionHasErrors(['tin' => 'This TIN is already registered.']);

        // One TIN is one business, so a seller's TIN cannot be reused either.
        $seller = User::factory()->create(['role' => 'seller']);
        SellerApplication::create(['user_id' => $seller->id, 'shop_name' => 'RV Gadget Hub', 'shop_category' => 'electronics', 'seller_type' => 'corporation',
            'tin' => '456-789-012-000', 'region_code' => '130000000', 'region_name' => 'NCR', 'city_code' => '137404000', 'city_name' => 'Quezon City',
            'barangay_code' => '137404001', 'barangay_name' => 'Alicia', 'street_address' => '2F', 'postal_code' => '1121']);
        $other(['tin' => '456-789-012-000'])->assertSessionHasErrors(['tin' => 'This TIN is already registered.']);
    }

    public function test_admin_reviews_documents_rejects_with_a_reason_and_the_partner_resubmits(): void
    {
        $owner = $this->signUp($this->signupData());
        $provider = $owner->logisticsProvider;
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->get(route('admin.logistics'))->assertOk()->assertSee(route('admin.logistics.show', $provider));
        $this->get(route('admin.logistics.show', $provider))->assertOk()
            ->assertSee('CS202012345')->assertSee('234-567-890-000')->assertSee('Operations Manager')->assertSee('Quezon City')
            ->assertSee(route('admin.logistics.document', [$provider, 'business_permit']));
        $this->get(route('admin.logistics.document', [$provider, 'business_permit']))->assertOk();

        // The partner sees the reason, so rejecting needs one.
        $this->patch(route('admin.users.status', $owner), ['status' => 'rejected'])->assertSessionHasErrors('reason');
        $this->patch(route('admin.users.status', $owner), ['status' => 'rejected', 'reason' => "Mayor's permit expired on 12/31/2025."])->assertSessionHasNoErrors();
        $this->assertSame("Mayor's permit expired on 12/31/2025.", $provider->fresh()->rejection_reason);
        $this->post(route('logout'));

        // Signing in shows the reason and the resubmit form instead of a generic error.
        $this->post(route('logistics.login.submit'), ['email' => $owner->email, 'password' => 'Hatid@2026'])->assertRedirect(route('logistics.application'));
        $this->get(route('logistics.application'))->assertOk()
            ->assertSee('Your application was not approved')->assertSee('Mayor&#039;s permit expired on 12/31/2025.', false)->assertSee('Resubmit application');

        $oldPermit = $provider->fresh()->business_permit_path;
        $this->post(route('logistics.application.update'), $this->applicationFields([
            'business_permit' => UploadedFile::fake()->create('mayors_permit_2026.pdf', 1200, 'application/pdf'),
        ]) + ['sec_certificate' => null, 'bir_2303' => null, 'representative_id' => null])->assertSessionHasNoErrors()->assertRedirect(route('logistics.application'));

        $provider->refresh();
        $this->assertSame('pending', $owner->fresh()->status);
        $this->assertSame('pending', $provider->status);
        $this->assertNull($provider->rejection_reason);
        $this->assertNotSame($oldPermit, $provider->business_permit_path);
        Storage::disk('local')->assertMissing($oldPermit);
        $this->post(route('logout'));
        $this->actingAs($admin)->get(route('admin.registrations', ['role' => 'logistics']))->assertOk()->assertSee('Bilis Hatid Express Inc.');
    }

    public function test_signing_in_before_entering_the_code_reopens_the_code_page(): void
    {
        $this->post(route('register'), $this->signupData())->assertRedirect(route('verification.notice'));
        $this->post(route('logout'));
        $this->flushSession();

        $this->post(route('logistics.login.submit'), ['email' => 'operations@bilishatid.example.com', 'password' => 'Hatid@2026'])
            ->assertRedirect(route('verification.notice'))->assertSessionHas('status', fn ($message) => str_contains($message, 'verify your email'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('operations@bilishatid.example.com');
        $this->post(route('verification.verify'), ['otp' => $this->signUpCode('operations@bilishatid.example.com')])->assertRedirect(route('logistics.application'));

        // A wrong password still gets the usual answer.
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'nobody@example.com', 'password' => 'Hatid@2026'])->assertSessionHasErrors(['email' => 'Incorrect email or password.']);
    }

    public function test_rider_dashboard_names_the_logistics_partner(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Bilis Hatid Express Inc.', 'slug' => 'bilis', 'status' => 'approved']);
        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => 'Pedro Jose Garcia']);
        Rider::create(['user_id' => $riderUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved']);

        $this->actingAs($riderUser)->get(route('courier.dashboard'))->assertOk()->assertSee('Pedro Jose Garcia')->assertSee('Bilis Hatid Express Inc.');
    }

    private function signupData(array $overrides = []): array
    {
        return array_replace([
            'role' => 'logistics', 'first_name' => 'Carlos Antonio', 'last_name' => 'Mendoza', 'email' => 'operations@bilishatid.example.com',
            'phone' => '09285551234', 'password' => 'Hatid@2026', 'password_confirmation' => 'Hatid@2026', 'terms' => '1',
        ], $this->applicationFields(), $overrides);
    }

    private function applicationFields(array $overrides = []): array
    {
        return array_replace([
            'company_name' => 'Bilis Hatid Express Inc.', 'sec_registration_no' => 'CS202012345', 'tin' => '234-567-890-000', 'contact_position' => 'Operations Manager',
            'service_areas' => ['NCR', 'IV-A'], 'region_code' => '130000000', 'province_code' => '', 'city_code' => '137404000', 'barangay_code' => '137404001',
            'street_address' => 'Unit 5B, 88 West Capitol Drive', 'postal_code' => '1603',
            'sec_certificate' => UploadedFile::fake()->create('sec_certificate.pdf', 1000, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('mayors_permit.pdf', 1300, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->create('govt_id_front.jpg', 1200, 'image/jpeg'),
        ], $overrides);
    }
}
