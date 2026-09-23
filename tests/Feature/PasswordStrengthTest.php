<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordStrengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_passwords_missing_any_required_part(): void
    {
        foreach (['Aa!1234', 'lowercase!', 'UPPERCASE!', 'MixedCase8', 'Mixed Case8'] as $weakPassword) {
            $this->post(route('register'), [
                'first_name' => 'Test',
                'last_name' => 'Shopper',
                'email' => 'shopper@example.com',
                'phone' => '09171234567',
                'date_of_birth' => '2000-09-15',
                'role' => 'buyer',
                'password' => $weakPassword,
                'password_confirmation' => $weakPassword,
            ])->assertSessionHasErrors('password');
        }

        $this->assertDatabaseMissing('users', ['email' => 'shopper@example.com']);
    }

    public function test_profile_password_change_requires_the_same_strength(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $originalHash = $buyer->password;

        $this->actingAs($buyer)->put(route('buyer.account.password'), [
            'current_password' => 'password',
            'password' => 'alllowercase!',
            'password_confirmation' => 'alllowercase!',
        ])->assertSessionHasErrors('password');

        $this->assertSame($originalHash, $buyer->fresh()->password);
    }

    public function test_password_reset_rejects_weak_passwords_and_accepts_strong_ones(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Mixed Case8',
            'password_confirmation' => 'Mixed Case8',
        ])->assertSessionHasErrors('password');
        $this->assertSame($originalHash, $user->fresh()->password);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewSecure!',
            'password_confirmation' => 'NewSecure!',
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewSecure!', $user->fresh()->password));
    }
}
