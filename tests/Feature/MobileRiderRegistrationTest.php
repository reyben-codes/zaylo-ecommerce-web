<?php

namespace Tests\Feature;

use App\Http\Requests\MobileRiderRegistrationRequest;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Services\PsgcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesSignUp;
use Tests\TestCase;

/**
 * Rider sign-up from the app creates the same rider application as the
 * website: the rider applies to a logistics partner, enters the emailed
 * code (which creates the application), and the partner approves them.
 */
class MobileRiderRegistrationTest extends TestCase
{
    use CompletesSignUp;
    use RefreshDatabase;

    private User $partnerOwner;

    private LogisticsProvider $partner;

    protected function setUp(): void
    {
        parent::setUp();
        // Location names normally come from the PSGC website; tests stay offline.
        $this->mock(PsgcService::class, fn ($mock) => $mock->shouldReceive('addressNames')->andReturn([
            'region_name' => 'CALABARZON', 'province' => 'Laguna', 'city' => 'City of Calamba', 'barangay' => 'Halang',
        ]));
        $this->partnerOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        $this->partner = LogisticsProvider::create([
            'user_id' => $this->partnerOwner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved',
        ]);
    }

    /** A 1x1 PNG, so the tests do not need the GD extension. */
    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1995-04-12',
            'gender' => 'male',
            'region_code' => '040000000',
            'province_code' => '043400000',
            'city_municipality_code' => '043405000',
            'barangay_code' => '043405029',
            'line1' => '12 Rizal St.',
            'postal_code' => '4027',
            'email' => 'juan.rider@example.test',
            'phone' => '09171234567',
            'password' => 'Zaylo#2026',
            'password_confirmation' => 'Zaylo#2026',
            'logistics_provider_id' => $this->partner->id,
            'vehicle_type' => 'Motorcycle',
            'license_number' => 'n01-23-456789',
            'license_expires_on' => now()->addYear()->toDateString(),
            'plate_number' => 'nbc 1234',
            'terms_accepted' => '1',
            'license_image' => $this->photo('license.png'),
            'or_cr_image' => $this->photo('or-cr.png'),
        ], $overrides);
    }

    private function register(array $overrides = [])
    {
        return $this->post('/api/mobile/rider/register', $this->payload($overrides), ['Accept' => 'application/json']);
    }

    /** Registers and enters the emailed code, so the application exists. Requires Notification::fake(). */
    private function apply(array $overrides = []): void
    {
        $this->register($overrides)->assertCreated()->assertJsonPath('verification_sent', true);
        $email = $this->payload($overrides)['email'];
        $this->postJson('/api/mobile/verify-email', ['email' => $email, 'otp' => $this->signUpCode($email)])->assertOk();
    }

    public function test_sign_up_lists_partners_accepting_riders_and_the_vehicle_types(): void
    {
        $closedOwner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        LogisticsProvider::create(['user_id' => $closedOwner->id, 'name' => 'Pending Partner', 'slug' => 'pending-partner', 'status' => 'pending']);

        $this->getJson('/api/mobile/rider/logistics-partners')
            ->assertOk()
            ->assertJsonCount(1, 'partners')
            ->assertJsonPath('partners.0.name', 'Express Delivery')
            ->assertJsonPath('vehicle_types', Rider::VEHICLE_TYPES)
            ->assertJsonPath('unlicensed_vehicle_types', ['Bicycle']);
    }

    public function test_rider_applies_to_a_logistics_partner_with_private_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();

        $this->register()
            ->assertCreated()
            ->assertJsonPath('email', 'juan.rider@example.test')
            ->assertJsonPath('verification_sent', true);
        // Nothing is saved until the emailed code is entered.
        $this->assertFalse(User::where('email', 'juan.rider@example.test')->exists());

        $this->postJson('/api/mobile/verify-email', ['email' => 'juan.rider@example.test', 'otp' => $this->signUpCode('juan.rider@example.test')])
            ->assertOk()
            ->assertJsonMissingPath('token')
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'Express Delivery'));

        $user = User::where('email', 'juan.rider@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('rider'));
        $this->assertSame('pending', $user->status);
        $this->assertSame('1995-04-12', $user->date_of_birth->toDateString());
        $this->assertSame('male', $user->gender);
        $this->assertSame('12 Rizal St., Halang, City of Calamba, Laguna, CALABARZON, 4027', $user->addresses()->sole()->formatted());

        $rider = $user->rider;
        $this->assertSame($this->partner->id, (int) $rider->logistics_provider_id);
        $this->assertSame('N01-23-456789', $rider->license_number);
        $this->assertSame('Motorcycle', $rider->vehicle_type);
        $this->assertSame('NBC1234', $rider->plate_no);
        $this->assertSame('pending', $rider->status);
        // Stored where the logistics partner's Riders page opens them, and never public.
        Storage::disk('local')->assertExists([$rider->license_document_path, $rider->or_cr_document_path]);
        $this->assertStringStartsWith("riders/{$user->id}/", $rider->license_document_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_bicycle_riders_need_no_license_plate_or_or_cr(): void
    {
        Storage::fake('local');
        Notification::fake();

        $this->apply([
            'vehicle_type' => 'Bicycle', 'license_number' => '', 'license_expires_on' => '', 'plate_number' => '',
            'license_image' => null, 'or_cr_image' => null,
        ]);

        $rider = Rider::sole();
        $this->assertSame('Bicycle', $rider->vehicle_type);
        $this->assertNull($rider->plate_no);
        $this->assertNull($rider->license_document_path);
    }

    public function test_registration_rules_match_the_app_form(): void
    {
        Storage::fake('local');

        $this->register([
            'first_name' => 'J',
            'last_name' => str_repeat('a', 51),
            'date_of_birth' => now()->subYears(16)->toDateString(),
            'logistics_provider_id' => 999,
            'license_expires_on' => now()->subDay()->toDateString(),
            'vehicle_type' => 'Rocket',
            'license_image' => null,
            'or_cr_image' => UploadedFile::fake()->createWithContent('or-cr.gif', base64_decode('R0lGODlhAQABAAAAACw=')),
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'first_name' => 'at least 2 characters',
            'last_name' => 'not be greater than 50 characters',
            'date_of_birth' => 'at least 18',
            'logistics_provider_id' => 'not accepting riders',
            'license_expires_on' => 'expired',
            'vehicle_type',
            'license_image' => 'Upload a photo',
            'or_cr_image' => 'upload a JPG, PNG or PDF',
        ]);

        $this->assertSame(1, User::count(), 'only the logistics partner exists');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_check_endpoint_reports_taken_details_and_formats_before_upload(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->apply();

        $details = collect($this->payload())->except(['license_image', 'or_cr_image'])->all();

        // Same email, phone, license, and plate as the rider who just registered.
        $this->postJson('/api/mobile/rider/register/check', $details)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'already registered',
                'phone' => 'already registered',
                'license_number' => 'already registered',
                'plate_number' => 'already registered',
            ]);

        $this->postJson('/api/mobile/rider/register/check', array_merge($details, [
            'email' => 'no-extension@example',
            'phone' => '19171234567',
            'license_number' => '001-23-456789',
            'plate_number' => 'ABCDEF',
            'license_expires_on' => now(MobileRiderRegistrationRequest::TIMEZONE)->subDay()->toDateString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'email' => 'valid email address',
            'phone' => 'starting with 09',
            'license_number' => 'A00-00-000000',
            'plate_number' => '5 to 7 letters and numbers',
            'license_expires_on' => 'expired',
        ]);

        // A license expiring today is still valid; dashes and case are normalized.
        $this->postJson('/api/mobile/rider/register/check', array_merge($details, [
            'email' => 'second.rider@example.com',
            'phone' => '09181234567',
            'license_number' => 'm9876543210',
            'plate_number' => 'xyz 789',
            'license_expires_on' => now(MobileRiderRegistrationRequest::TIMEZONE)->toDateString(),
        ]))->assertOk()->assertJson(['valid' => true]);
    }

    public function test_documents_over_five_megabytes_are_rejected(): void
    {
        Storage::fake('local');

        $this->register(['license_image' => UploadedFile::fake()->create('license.pdf', 5121, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_image']);
    }

    public function test_application_requires_accepting_the_rider_terms(): void
    {
        Storage::fake('local');

        $this->register(['terms_accepted' => '0'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['terms_accepted' => 'Rider Terms and Conditions']);

        $this->assertDatabaseCount('riders', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_pending_rider_cannot_sign_in_until_the_partner_approves(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->apply();

        $this->postJson('/api/mobile/rider/login', ['email' => 'juan.rider@example.test', 'password' => 'Zaylo#2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'still being reviewed by Express Delivery']);
    }

    public function test_partner_approval_lets_the_rider_sign_in(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->apply();
        $rider = Rider::sole();

        // The logistics partner approves the app sign-up from its Riders page on the website.
        $this->actingAs($this->partnerOwner)->patch(route('logistics.riders.update', $rider), ['action' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $rider->fresh()->status);
        $user = $rider->user->fresh();
        $this->assertSame('active', $user->status);

        // The email was confirmed with the sign-up code, so approval is all the rider waits for.
        $this->postJson('/api/mobile/rider/login', ['email' => $user->email, 'password' => 'Zaylo#2026'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_rejected_rider_sees_the_reason_when_signing_in(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->apply();
        $rider = Rider::sole();

        $this->actingAs($this->partnerOwner)->patch(route('logistics.riders.update', $rider), [
            'action' => 'reject', 'reason' => 'License photo is unreadable.',
        ])->assertRedirect();

        $this->postJson('/api/mobile/rider/login', ['email' => 'juan.rider@example.test', 'password' => 'Zaylo#2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'not approved by Express Delivery: License photo is unreadable.']);
    }
}
