<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
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
            'phone' => '0917 555 0101',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', 'Profile settings updated.');

        $buyer->refresh();
        $this->assertSame('Updated Buyer', $buyer->name);
        $this->assertSame('0917 555 0101', $buyer->phone);
        $this->assertTrue($buyer->hasVerifiedEmail());
    }

    public function test_changed_email_must_be_unique_and_is_reverified(): void
    {
        Notification::fake();
        $buyer = $this->buyer();
        $other = User::factory()->create();

        $this->patch(route('buyer.account.profile'), [
            'name' => $buyer->name,
            'email' => $other->email,
            'phone' => null,
        ])->assertSessionHasErrors('email');

        $this->patch(route('buyer.account.profile'), [
            'name' => $buyer->name,
            'email' => 'new-buyer@example.com',
            'phone' => null,
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
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $buyer->fresh()->password));

        $this->put(route('buyer.account.password'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', 'Password updated.');
        $this->assertTrue(Hash::check('new-password', $buyer->fresh()->password));
    }

    public function test_google_linked_buyer_can_create_a_password_but_not_change_google_email(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'status' => 'active',
            'google_id' => 'google-buyer-123',
            'auth_provider' => 'google',
            'password' => null,
        ]);
        $this->actingAs($buyer);

        $this->patch(route('buyer.account.profile'), [
            'name' => $buyer->name,
            'email' => 'changed@example.com',
            'phone' => null,
        ])->assertSessionHasErrors('email');

        $this->put(route('buyer.account.password'), [
            'password' => 'local-password',
            'password_confirmation' => 'local-password',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('local-password', $buyer->fresh()->password));
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

    public function test_non_buyers_cannot_update_buyer_profile_settings(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        $this->actingAs($seller)->patch(route('buyer.account.profile'), [
            'name' => 'Not allowed',
            'email' => $seller->email,
            'phone' => null,
        ])->assertForbidden();
        $this->assertNotSame('Not allowed', $seller->fresh()->name);
    }

    private function buyer(): User
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer);

        return $buyer;
    }
}
