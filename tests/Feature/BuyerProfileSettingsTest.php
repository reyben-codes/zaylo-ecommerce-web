<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class BuyerProfileSettingsTest extends TestCase
{
    use PsgcFixtures, RefreshDatabase;

    public function test_buyer_can_view_and_update_profile_settings(): void
    {
        $buyer = $this->buyer();
        $buyer->addresses()->create($this->savedAddressData(['label' => 'Work', 'is_default' => true]));

        $this->get(route('buyer.account'))
            ->assertOk()
            ->assertSee('Profile Settings')
            ->assertSee('Buyer addresses')
            ->assertSee('Add Home')
            ->assertSee('Work');

        $this->patch(route('buyer.account.profile'), [
            'name' => 'Updated Buyer',
            'email' => $buyer->email,
            'phone' => '09175550101',
            'date_of_birth' => '2000-09-15',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', 'Profile settings updated.');

        $buyer->refresh();
        $this->assertSame('Updated Buyer', $buyer->name);
        $this->assertSame('09175550101', $buyer->phone);
        $this->assertSame('2000-09-15', $buyer->date_of_birth->toDateString());
        $this->assertTrue($buyer->hasVerifiedEmail());
    }

    public function test_profile_rejects_numbered_names_and_phone_letters_without_saving_them(): void
    {
        $buyer = $this->buyer();
        $originalName = $buyer->name;
        $originalPhone = $buyer->phone;

        foreach ([
            ['name' => 'Buyer42', 'phone' => '09175550101', 'invalid' => 'name'],
            ['name' => 'Updated Buyer', 'phone' => '0917CALL123', 'invalid' => 'phone'],
            ['name' => 'Updated Buyer', 'phone' => '-------', 'invalid' => 'phone'],
            ['name' => 'Updated Buyer', 'phone' => '0917555010', 'invalid' => 'phone'],
            ['name' => 'Updated Buyer', 'phone' => '091755501012', 'invalid' => 'phone'],
            ['name' => 'Updated Buyer', 'phone' => '08175550101', 'invalid' => 'phone'],
        ] as $case) {
            $this->patch(route('buyer.account.profile'), [
                'name' => $case['name'],
                'email' => $buyer->email,
                'phone' => $case['phone'],
                'date_of_birth' => '2000-09-15',
            ])->assertSessionHasErrors($case['invalid']);

            $buyer->refresh();
            $this->assertSame($originalName, $buyer->name);
            $this->assertSame($originalPhone, $buyer->phone);
        }
    }

    public function test_profile_requires_a_mobile_number(): void
    {
        $buyer = $this->buyer();

        $this->patch(route('buyer.account.profile'), [
            'name' => 'Updated Buyer',
            'email' => $buyer->email,
            'phone' => null,
            'date_of_birth' => '2000-09-15',
        ])->assertSessionHasErrors('phone');

        $this->assertSame('09170000000', $buyer->fresh()->phone);
        $this->assertNotSame('Updated Buyer', $buyer->fresh()->name);
    }

    public function test_changed_email_must_be_unique_and_is_reverified(): void
    {
        Notification::fake();
        $buyer = $this->buyer();
        $other = User::factory()->create();

        $this->patch(route('buyer.account.profile'), [
            'name' => $buyer->name,
            'email' => $other->email,
            'phone' => $buyer->phone,
            'date_of_birth' => '2000-09-15',
        ])->assertSessionHasErrors('email');

        $this->patch(route('buyer.account.profile'), [
            'name' => $buyer->name,
            'email' => 'new-buyer@example.com',
            'phone' => $buyer->phone,
            'date_of_birth' => '2000-09-15',
        ])->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();

        $buyer->refresh();
        $this->assertSame('new-buyer@example.com', $buyer->email);
        $this->assertFalse($buyer->hasVerifiedEmail());
        Notification::assertSentTo($buyer, VerifyEmailCode::class);
    }

    public function test_buyer_can_securely_change_a_local_password(): void
    {
        $buyer = $this->buyer();

        $this->put(route('buyer.account.password'), [
            'current_password' => 'incorrect',
            'password' => 'New-password!',
            'password_confirmation' => 'New-password!',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $buyer->fresh()->password));

        $this->put(route('buyer.account.password'), [
            'current_password' => 'password',
            'password' => 'New-password!',
            'password_confirmation' => 'New-password!',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', 'Password updated.');
        $this->assertTrue(Hash::check('New-password!', $buyer->fresh()->password));
    }

    public function test_google_linked_buyer_can_create_a_password_but_not_change_google_email(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'status' => 'active',
            'google_id' => 'google-buyer-123',
            'auth_provider' => 'google',
            'password' => null,
            'phone' => '09170000000',
        ]);
        $this->actingAs($buyer);

        $this->patch(route('buyer.account.profile'), [
            'name' => $buyer->name,
            'email' => 'changed@example.com',
            'phone' => $buyer->phone,
            'date_of_birth' => '2000-09-15',
        ])->assertSessionHasErrors('email');

        $this->put(route('buyer.account.password'), [
            'password' => 'Local-password!',
            'password_confirmation' => 'Local-password!',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Local-password!', $buyer->fresh()->password));
    }

    public function test_address_type_shortcut_preselects_the_requested_type(): void
    {
        $this->buyer();

        $this->get(route('addresses.create', ['type' => 'School']))
            ->assertOk()
            ->assertSee('name="label" value="School" checked', false);

        $this->get(route('addresses.create', ['type' => 'Invalid']))
            ->assertOk()
            ->assertSee('name="label" value="Home" checked', false);
    }

    public function test_buyer_age_updates_automatically_on_their_birthday(): void
    {
        Carbon::setTestNow('2026-09-14 12:00:00');

        try {
            $buyer = User::factory()->create([
                'role' => 'buyer',
                'status' => 'active',
                'date_of_birth' => '2000-09-15',
            ]);

            $this->assertSame(25, $buyer->age);

            Carbon::setTestNow('2026-09-15 12:00:00');

            $this->assertSame(26, $buyer->age);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_non_buyers_cannot_update_buyer_profile_settings(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->actingAs($seller)->patch(route('buyer.account.profile'), [
            'name' => 'Not allowed',
            'email' => $seller->email,
            'phone' => '09170000000',
        ])->assertForbidden();
        $this->assertNotSame('Not allowed', $seller->fresh()->name);
    }

    private function buyer(): User
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'phone' => '09170000000']);
        $this->actingAs($buyer);

        return $buyer;
    }
}
