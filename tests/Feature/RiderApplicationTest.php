<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesSignUp;
use Tests\TestCase;

/**
 * Desktop rider sign-up (Register > Become a rider): birthday, LTO license, expiry, plate and documents,
 * reviewed by the logistics partner the rider chooses.
 */
class RiderApplicationTest extends TestCase
{
    use CompletesSignUp, RefreshDatabase;

    private LogisticsProvider $partner;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->partner = $this->partner('Bilis Hatid Express Inc.');
    }

    public function test_rider_applies_with_license_vehicle_and_documents(): void
    {
        $this->get(route('register', ['role' => 'courier']))->assertOk()
            ->assertSee('name="date_of_birth"', false)->assertSee('name="license_expires_on"', false)
            ->assertSee('name="license_document"', false)->assertSee('name="or_cr_document"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->apply(['license_number' => 'n0123456789', 'plate_no' => '123 abc'])->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));
        // Nothing is saved until the emailed code is entered.
        $this->assertDatabaseMissing('users', ['email' => 'pedrojose.garcia@example.com']);
        $this->assertDatabaseCount('riders', 0);
        $this->post(route('verification.verify'), ['otp' => $this->signUpCode('pedrojose.garcia@example.com')])->assertRedirect(route('rider.login'));

        $user = User::where('email', 'pedrojose.garcia@example.com')->firstOrFail();
        $rider = Rider::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('1995-03-22', $user->date_of_birth->toDateString());
        $this->assertSame('N01-23-456789', $rider->license_number);
        $this->assertSame('123ABC', $rider->plate_no);
        $this->assertSame($this->partner->id, $rider->logistics_provider_id);
        Storage::disk('local')->assertExists([$rider->license_document_path, $rider->or_cr_document_path]);
    }

    public function test_license_plate_birthday_and_documents_are_validated(): void
    {
        $this->applied();

        $attempt = function (array $overrides) {
            $this->travel(1)->minutes(); // sign-up allows 6 attempts a minute

            return $this->apply($overrides + ['email' => 'mark@example.com', 'phone' => '09662345678', 'license_number' => 'D05-19-123456', 'plate_no' => 'NBC1234']);
        };
        $attempt(['license_number' => 'N01-23-45678'])->assertSessionHasErrors(['license_number' => "Enter your driver's license number in the LTO format A00-00-000000."]);
        $attempt(['license_number' => '101-23-456789'])->assertSessionHasErrors('license_number');
        $attempt(['license_number' => 'N01-23-456789'])->assertSessionHasErrors(['license_number' => "This driver's license number is already registered."]);
        $attempt(['license_expires_on' => today()->subDay()->toDateString()])->assertSessionHasErrors(['license_expires_on' => "Your driver's license has expired. Renew it before applying."]);
        $attempt(['plate_no' => 'ABCDEF'])->assertSessionHasErrors('plate_no');
        $attempt(['plate_no' => '123 ABC'])->assertSessionHasErrors(['plate_no' => 'This plate number is already registered to another rider.']);
        $attempt(['date_of_birth' => today()->subYears(18)->addDay()->toDateString()])->assertSessionHasErrors(['date_of_birth' => 'You must be at least 18 years old to register.']);
        $attempt(['license_document' => UploadedFile::fake()->create('price_list.docx', 320)])->assertSessionHasErrors(['license_document' => "Driver's license: upload a JPG, PNG or PDF file."]);
        $attempt(['or_cr_document' => UploadedFile::fake()->create('size_5.1MB.jpg', 5222, 'image/jpeg')])->assertSessionHasErrors(['or_cr_document' => 'Vehicle OR/CR: the file must be 5 MB or smaller.']);
        $this->assertDatabaseMissing('users', ['email' => 'mark@example.com']);

        $attempt(['license_expires_on' => today()->toDateString()])->assertSessionHasNoErrors();
    }

    public function test_bicycle_riders_need_no_license_plate_or_documents(): void
    {
        $this->applied(['vehicle_type' => 'Bicycle', 'license_number' => '', 'license_expires_on' => '', 'plate_no' => '', 'license_document' => null, 'or_cr_document' => null]);
        $rider = Rider::firstOrFail();
        $this->assertNull($rider->license_number);
        $this->assertNull($rider->license_document_path);
    }

    public function test_partner_reviews_documents_and_the_rider_sees_the_outcome(): void
    {
        $user = $this->applied();
        $rider = Rider::where('user_id', $user->id)->firstOrFail();

        $this->post(route('rider.login.submit'), ['email' => $user->email, 'password' => 'Rider@2026'])
            ->assertSessionHasErrors(['email' => 'Your rider application is waiting for Bilis Hatid Express Inc. to review it.']);

        $other = $this->partner('Agila Cargo Movers Corp.');
        $this->actingAs($other->owner)->get(route('logistics.riders.document', [$rider, 'license_document']))->assertForbidden();

        $this->actingAs($this->partner->owner)->get(route('logistics.riders', ['status' => 'pending']))->assertOk()
            ->assertSee('N01-23-456789')->assertSee('Expires')->assertSee('Age')->assertSee(route('logistics.riders.document', [$rider, 'or_cr_document']))
            ->assertSee('js/document-viewer.js', false)->assertSee('data-document-viewer', false)->assertSee('data-document-gallery', false);
        $this->get(route('logistics.riders.document', [$rider, 'license_document']))->assertOk();
        $this->patch(route('logistics.riders.update', $rider), ['action' => 'reject', 'reason' => 'The OR/CR photo is blurred.'])->assertSessionHasNoErrors();

        // A rejected rider cannot be approved afterwards, by the partner or by an admin.
        $this->get(route('logistics.riders', ['status' => 'rejected']))->assertOk()
            ->assertSee('a rejected application cannot be approved')->assertDontSee('name="action" value="approve"', false);
        $this->patch(route('logistics.riders.update', $rider), ['action' => 'approve'])->assertSessionHasErrors('action');
        $this->assertSame('rejected', $rider->fresh()->status);
        $this->assertSame('rejected', $user->fresh()->status);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.users.status', $user), ['status' => 'active'])->assertSessionHasErrors('status');
        $this->assertSame('rejected', $user->fresh()->status);
        $this->post(route('logout'));

        $this->post(route('rider.login.submit'), ['email' => $user->email, 'password' => 'Rider@2026'])
            ->assertSessionHasErrors(['email' => 'Bilis Hatid Express Inc. did not approve your rider application. Reason: The OR/CR photo is blurred.']);
    }

    /** Apply, enter the emailed code, and return the rider's account (signed out). */
    private function applied(array $overrides = []): User
    {
        $this->apply($overrides)->assertSessionHasNoErrors();
        $this->post(route('verification.verify'), ['otp' => $this->signUpCode('pedrojose.garcia@example.com')])->assertSessionHasNoErrors();
        if (auth()->check()) {
            $this->post(route('logout'));
        }

        return User::where('email', 'pedrojose.garcia@example.com')->firstOrFail();
    }

    private function apply(array $overrides = [])
    {
        $data = array_replace([
            'role' => 'courier', 'first_name' => 'Pedro Jose', 'last_name' => 'Garcia', 'email' => 'pedrojose.garcia@example.com',
            'phone' => '09451239876', 'date_of_birth' => '1995-03-22', 'password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026', 'terms' => '1',
            'logistics_provider_id' => $this->partner->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => '123ABC',
            'license_number' => 'N01-23-456789', 'license_expires_on' => today()->addYears(4)->toDateString(),
            'license_document' => UploadedFile::fake()->create('drivers_license.jpg', 1500, 'image/jpeg'),
            'or_cr_document' => UploadedFile::fake()->create('or_cr.pdf', 900, 'application/pdf'),
        ], $overrides);

        return $this->post(route('register'), array_filter($data, fn ($value) => $value !== null));
    }

    private function partner(string $name): LogisticsProvider
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);

        return LogisticsProvider::create([
            'user_id' => $owner->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name).'-'.$owner->id,
            'status' => 'approved', 'approved_at' => now(),
        ])->setRelation('owner', $owner);
    }
}
