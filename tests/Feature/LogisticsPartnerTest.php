<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesSignUp;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class LogisticsPartnerTest extends TestCase
{
    use CompletesSignUp, PsgcFixtures, RefreshDatabase;

    public function test_logistics_partner_registers_and_needs_admin_approval(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->fakeLocations();
        $owner = $this->signUp($this->registration('logistics', 'dispatch@example.com') + [
            'company_name' => 'Swift Parcels', 'sec_registration_no' => 'CS202012345', 'tin' => '234-567-890-000', 'contact_position' => 'Operations Manager',
            'service_areas' => ['NCR'], 'region_code' => '130000000', 'province_code' => '', 'city_code' => '137404000', 'barangay_code' => '137404001',
            'street_address' => 'Unit 5B, 88 West Capitol Drive', 'postal_code' => '1603',
            'sec_certificate' => UploadedFile::fake()->create('sec_certificate.pdf', 1000, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('mayors_permit.pdf', 1300, 'application/pdf'),
            'representative_id' => UploadedFile::fake()->create('govt_id_front.jpg', 1200, 'image/jpeg'),
        ]);
        $this->assertSame('logistics', $owner->role);
        $this->assertSame('pending', $owner->status);
        $this->assertDatabaseHas('logistics_providers', ['user_id' => $owner->id, 'name' => 'Swift Parcels', 'status' => 'pending']);

        // Not yet approved: the partner only sees its application and riders cannot pick it.
        $this->actingAs($owner)->get(route('logistics.dashboard'))->assertRedirect(route('logistics.application'));
        $this->get(route('register', ['role' => 'courier']))->assertDontSee('Swift Parcels');

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.registrations', ['role' => 'logistics']))->assertOk()->assertSee('Swift Parcels');
        $this->patch(route('admin.users.status', $owner), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $owner->logisticsProvider()->first()->status);

        $this->actingAs($owner->fresh())->get(route('logistics.dashboard'))->assertOk()->assertSee('Swift Parcels');
        $this->post(route('logout'));
        $this->get(route('register', ['role' => 'courier']))->assertOk()->assertSee('Swift Parcels');
    }

    public function test_rider_applies_to_a_partner_that_approves_them(): void
    {
        Notification::fake();
        Storage::fake('local');
        $partner = $this->approvedPartner('Swift Parcels');
        $other = $this->approvedPartner('Other Couriers');

        $riderUser = $this->signUp($this->registration('courier', 'rider@example.com') + [
            'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'abc 1234', 'license_number' => 'N01-23-456789',
            'date_of_birth' => '1995-03-22', 'license_expires_on' => today()->addYears(3)->toDateString(),
            'license_document' => UploadedFile::fake()->create('drivers_license.jpg', 1500, 'image/jpeg'),
            'or_cr_document' => UploadedFile::fake()->create('or_cr.pdf', 900, 'application/pdf'),
        ]);
        $this->assertSame('rider', $riderUser->role);
        $this->assertSame('pending', $riderUser->status);
        $rider = Rider::where('user_id', $riderUser->id)->firstOrFail();
        $this->assertSame('pending', $rider->status);
        $this->assertSame('ABC1234', $rider->plate_no);
        Storage::disk('local')->assertExists([$rider->license_document_path, $rider->or_cr_document_path]);
        $this->assertStringStartsWith("riders/{$riderUser->id}/", $rider->license_document_path);

        // Another partner cannot review this rider; the chosen partner sees the application.
        $this->actingAs($other->owner)->patch(route('logistics.riders.update', $rider), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($partner->owner)->get(route('logistics.riders'))->assertOk()->assertSee($riderUser->name)->assertSee('Motorcycle');
        $this->patch(route('logistics.riders.update', $rider), ['action' => 'approve'])->assertSessionHasNoErrors();

        $this->assertSame('approved', $rider->fresh()->status);
        $this->assertSame('active', $riderUser->fresh()->status);
        $this->actingAs($riderUser->fresh())->get(route('courier.dashboard'))->assertOk();

        $this->actingAs($partner->owner)->patch(route('logistics.riders.update', $rider), ['action' => 'suspend', 'reason' => 'Missed pickups'])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $riderUser->fresh()->status);
        $this->assertSame('Missed pickups', $rider->fresh()->rejection_reason);
        $this->patch(route('logistics.riders.update', $rider), ['action' => 'reject'])->assertSessionHasErrors('action');
    }

    public function test_riders_must_choose_an_approved_partner(): void
    {
        Notification::fake();
        $pending = LogisticsProvider::create([
            'user_id' => User::factory()->create(['role' => 'logistics', 'status' => 'pending'])->id,
            'name' => 'Not Yet Approved', 'slug' => 'not-yet-approved', 'status' => 'pending',
        ]);

        $this->post(route('register'), $this->registration('courier', 'rider@example.com') + ['vehicle_type' => 'Motorcycle', 'plate_no' => 'X1'])
            ->assertSessionHasErrors('logistics_provider_id');
        $this->post(route('register'), $this->registration('courier', 'rider@example.com') + [
            'logistics_provider_id' => $pending->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'X1',
        ])->assertSessionHasErrors('logistics_provider_id');
        $this->assertDatabaseMissing('users', ['email' => 'rider@example.com']);
    }

    public function test_admin_pages_render_and_only_admins_can_open_them(): void
    {
        $admin = $this->admin();
        $this->approvedPartner('Swift Parcels');

        foreach (['admin.dashboard', 'admin.registrations', 'admin.users', 'admin.logistics', 'admin.account', 'admin.reports'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee('Admin Center');
        }
        $this->get(route('admin.users', ['role' => 'logistics']))->assertOk()->assertSee('Swift Parcels');

        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_provision_command_creates_an_admin_who_can_sign_in(): void
    {
        $this->assertSame(0, Artisan::call('admin:provision', ['email' => 'Owner@Example.com', '--name' => 'Store Owner']));
        preg_match('/\|\s*Password\s*\|\s*(\S+)\s*\|/', Artisan::output(), $matches);

        $this->post(route('admin.login.submit'), ['email' => 'owner@example.com', 'password' => $matches[1]])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();

        User::factory()->create(['role' => 'buyer', 'email' => 'buyer@example.com']);
        $this->assertSame(1, Artisan::call('admin:provision', ['email' => 'buyer@example.com']));
    }

    public function test_logistics_provision_command_creates_an_approved_partner_who_can_sign_in(): void
    {
        $this->assertSame(0, Artisan::call('logistics:provision', ['email' => 'dispatch@example.com', '--company' => 'Swift Parcels', '--phone' => '09170000009']));
        preg_match('/\|\s*Password\s*\|\s*(\S+)\s*\|/', Artisan::output(), $matches);

        $this->post(route('logistics.login.submit'), ['email' => 'dispatch@example.com', 'password' => $matches[1]])->assertRedirect(route('logistics.dashboard'));
        $this->get(route('logistics.dashboard'))->assertOk()->assertSee('Swift Parcels');
        $this->post(route('logout'));
        $this->get(route('register', ['role' => 'courier']))->assertSee('Swift Parcels');
    }

    public function test_logistics_partners_have_their_own_sign_in_page(): void
    {
        $this->get(route('logistics.dashboard'))->assertRedirect(route('logistics.login'));
        $this->get(route('logistics.login'))->assertOk()
            ->assertSee('ZAYLO LOGISTICS CENTER')
            ->assertSee('action="'.route('logistics.login.submit').'"', false)
            ->assertSee(route('register', ['role' => 'logistics']))
            ->assertDontSee('Continue with Google');
        $this->get(route('login'))->assertOk()->assertSee(route('logistics.login'));
        $this->get(route('register', ['role' => 'logistics']))->assertSee(route('logistics.login'));

        $partner = $this->approvedPartner('Swift Parcels');
        $partner->owner->forceFill(['password' => 'Partner-pass1!'])->save();
        $this->post(route('logistics.login.submit'), ['email' => $partner->owner->email, 'password' => 'Partner-pass1!'])
            ->assertRedirect(route('logistics.dashboard'));
        $this->post(route('logout'))->assertRedirect(route('logistics.login'));

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'password' => 'Buyer-pass1!']);
        $this->from(route('logistics.login'))->post(route('logistics.login.submit'), ['email' => $buyer->email, 'password' => 'Buyer-pass1!'])
            ->assertRedirect(route('logistics.login'))
            ->assertSessionHasErrors(['email' => "This is a buyer account, so it can't sign in here. Please use the shop sign-in page instead."])
            ->assertSessionHas('portal_hint.url', route('login'));
        $this->assertGuest();

        // Applicants under review see their application; suspended partners are turned away.
        $pending = User::factory()->create(['role' => 'logistics', 'status' => 'pending', 'password' => 'Pending-pass1!']);
        $this->post(route('logistics.login.submit'), ['email' => $pending->email, 'password' => 'Pending-pass1!'])
            ->assertRedirect(route('logistics.application'));
        $this->post(route('logout'));
        $suspended = User::factory()->create(['role' => 'logistics', 'status' => 'suspended', 'password' => 'Suspended-pass1!']);
        $this->post(route('logistics.login.submit'), ['email' => $suspended->email, 'password' => 'Suspended-pass1!'])
            ->assertSessionHasErrors(['email' => 'This logistics account is pending approval or has been suspended.']);
        $this->assertGuest();
    }

    public function test_admins_have_their_own_sign_in_page_that_is_not_linked_publicly(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()
            ->assertSee('ZAYLO ADMIN CENTER')
            ->assertSee('action="'.route('admin.login.submit').'"', false)
            ->assertDontSee('Continue with Google');
        $this->get(route('login'))->assertSeeInOrder(['Sign in to your deliveries', 'Administrator?', route('admin.login')]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'password' => 'Admin-pass1!']);
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'Admin-pass1!'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post(route('logout'))->assertRedirect(route('admin.login'));

        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'password' => 'Seller-pass1!']);
        $this->post(route('admin.login.submit'), ['email' => $seller->email, 'password' => 'Seller-pass1!'])
            ->assertSessionHasErrors(['email' => "This is a seller account, so it can't sign in here. Please use the Seller Center sign-in page instead."]);
        $this->assertGuest();
    }

    private function approvedPartner(string $name): LogisticsProvider
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);

        return LogisticsProvider::create([
            'user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved', 'contact_phone' => '09170000001',
        ])->load('owner');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function registration(string $role, string $email): array
    {
        return [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'role' => $role, 'email' => $email,
            'password' => 'Strong-password1!', 'password_confirmation' => 'Strong-password1!', 'terms' => '1',
            // Mobile numbers are unique, so derive one per email.
            'phone' => '0917'.str_pad((string) (crc32($email) % 10000000), 7, '0', STR_PAD_LEFT),
        ];
    }
}
