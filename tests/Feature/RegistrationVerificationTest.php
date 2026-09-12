<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_hides_account_type_and_preserves_partner_signup(): void
    {
        foreach (['buyer', 'seller', 'courier'] as $role) {
            $parameters = $role === 'buyer' ? [] : ['role' => $role];
            $this->get(route('register', $parameters))
                ->assertOk()
                ->assertDontSee('<select', false)
                ->assertDontSee('Account type')
                ->assertSee('type="hidden" name="role" id="selectedRole" value="'.$role.'"', false);
        }
    }

    public function test_registration_has_the_requested_step_order(): void
    {
        $response = $this->get(route('register'))->assertOk()->assertSeeInOrder([
            'Details', 'Sign-in', 'Verify',
            'Your personal details', 'Your sign-in details', 'Send verification code',
        ])->assertDontSee('name="address"', false)
            ->assertDontSee('Continue to address')
            ->assertDontSee('id="step3"', false)
            ->assertSee('id="step2-title"', false);
        $this->assertSame(3, substr_count($response->getContent(), 'data-step="'));
        $this->assertSame(2, substr_count($response->getContent(), 'class="step-content"'));
    }

    public function test_registration_ignores_submitted_addresses_for_every_role(): void
    {
        Notification::fake();
        foreach (['buyer', 'seller', 'courier'] as $role) {
            $data = $this->registration($role);
            // Even an invalid legacy address must not be validated or saved.
            $data['address'] = str_repeat('x', 300);
            $this->post(route('register'), $data)
                ->assertRedirect(route('verification.notice'))
                ->assertSessionHasNoErrors();
            $user = User::where('email', $data['email'])->firstOrFail();
            $this->assertNull($user->address);
            $this->assertSame($role, $user->role);
            $this->post(route('logout'));
        }
    }

    public function test_registration_errors_select_the_correct_step(): void
    {
        foreach (['first_name' => 1, 'last_name' => 1, 'phone' => 1, 'role' => 1, 'email' => 2, 'password' => 2, 'password_confirmation' => 2] as $field => $step) {
            $errors = new \Illuminate\Support\ViewErrorBag;
            $errors->put('default', new \Illuminate\Support\MessageBag([$field => 'Test error']));
            $response = $this->withSession(['errors' => $errors])->get(route('register'))->assertOk();
            $this->assertMatchesRegularExpression('/const firstErrorStep\s*=\s*'.$step.';/', $response->getContent());
        }
    }

    public function test_buyer_registers_then_verifies_with_the_emailed_code_once(): void
    {
        Notification::fake();
        Event::fake([Verified::class]);
        $this->post(route('register'), $this->registration())->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'shopper@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->address);
        $code = $this->codeFor($user);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->assertTrue(Hash::check($code, DB::table('email_verification_codes')->where('user_id', $user->id)->value('code_hash')));
        $this->get(route('verification.notice'))->assertOk()->assertSee('name="otp"', false)->assertSee($user->email)->assertDontSee($code)
            ->assertSee('data-step="3"  aria-current="step"', false)
            ->assertDontSee('data-step="4"', false);
        $this->get(route('buyer.dashboard'))->assertRedirect(route('verification.notice'));

        $this->post(route('verification.verify'), ['otp' => $code])->assertRedirect(route('buyer.dashboard'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
        $this->post(route('verification.verify'), ['otp' => $code])->assertRedirect(route('buyer.dashboard'));
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->post(route('verification.send'))->assertRedirect(route('buyer.dashboard'));
        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);
    }

    public function test_incorrect_codes_are_counted_and_locked_after_five_attempts(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->codeFor($user);
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('verification.notice'))->post(route('verification.verify'), ['otp' => $wrong])
                ->assertSessionHasErrors('otp')->assertSessionMissing('_old_input.otp');
            $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id, 'attempts' => $attempt]);
        }
        $this->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_codes_are_rejected(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->codeFor($user);
        $this->travel(10)->minutes();
        $this->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resending_requires_a_cooldown_and_replaces_the_old_challenge(): void
    {
        $user = $this->unverifiedUser();
        // Set a known old code to avoid relying on random codes being different.
        DB::table('email_verification_codes')->where('user_id', $user->id)->update(['code_hash' => Hash::make('old-code'), 'attempts' => 5]);
        $oldHash = DB::table('email_verification_codes')->where('user_id', $user->id)->value('code_hash');
        $this->from(route('verification.notice'))->post(route('verification.send'))->assertSessionHasErrors('otp');
        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);
        $this->travel(61)->seconds();
        $this->post(route('verification.send'))->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, VerifyEmailCode::class, 2);
        $newCode = Notification::sent($user, VerifyEmailCode::class)->last()->code;
        $newHash = DB::table('email_verification_codes')->where('user_id', $user->id)->value('code_hash');
        $this->assertNotSame($oldHash, $newHash);
        $this->assertFalse(Hash::check('old-code', $newHash));
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id, 'attempts' => 0]);
        $this->post(route('verification.verify'), ['otp' => $newCode])->assertRedirect(route('buyer.dashboard'));
    }

    public function test_codes_are_bound_to_the_account_and_its_email(): void
    {
        $user = $this->unverifiedUser();
        $code = $this->codeFor($user);
        $other = User::factory()->unverified()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertNull($other->fresh()->email_verified_at);
        $user->update(['email' => 'changed@example.com']);
        $this->actingAs($user)->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_pending_partners_can_verify_but_still_require_approval(): void
    {
        Notification::fake();
        foreach (['seller', 'courier'] as $role) {
            $this->post(route('register'), $this->registration($role))->assertRedirect(route('verification.notice'));
            $user = User::where('email', $role.'@example.com')->firstOrFail();
            $this->get(route('verification.notice'))->assertOk()->assertSee('administrator approval');
            $this->post(route('verification.verify'), ['otp' => $this->codeFor($user)])->assertRedirect(route('login'));
            $this->assertGuest();
            $this->assertNotNull($user->fresh()->email_verified_at);
            $this->assertSame('pending', $user->fresh()->status);
            $this->assertDatabaseHas($role.'_profiles', ['user_id' => $user->id]);
        }
    }

    public function test_mail_failure_keeps_the_account_and_allows_a_retry(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Mail transport unavailable'));
        $this->post(route('register'), $this->registration())->assertRedirect(route('verification.notice'))->assertSessionHasErrors('otp');
        $user = User::where('email', 'shopper@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
        Notification::fake();
        $this->from(route('verification.notice'))->post(route('verification.send'))->assertSessionHasNoErrors();
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_otp_requires_six_digits_and_preserves_leading_zeroes(): void
    {
        $user = $this->unverifiedUser();
        $this->post(route('verification.verify'), ['otp' => '12345'])->assertSessionHasErrors('otp');
        $this->post(route('verification.verify'), ['otp' => 'abcdef'])->assertSessionHasErrors('otp');
        DB::table('email_verification_codes')->where('user_id', $user->id)->update(['code_hash' => Hash::make('012345')]);
        $this->post(route('verification.verify'), ['otp' => '012345'])->assertRedirect(route('buyer.dashboard'));
    }

    public function test_verification_and_resend_require_authentication(): void
    {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
        $this->post(route('verification.verify'), ['otp' => '123456'])->assertRedirect(route('login'));
        $this->post(route('verification.send'))->assertRedirect(route('login'));
        $this->get('/email/verify/1/legacy-link')->assertNotFound();
    }

    public function test_notification_contains_the_code_and_expiry_in_a_mail_message(): void
    {
        $user = User::factory()->make();
        $notification = new VerifyEmailCode('012345');
        $this->assertSame(['mail'], $notification->via($user));
        $mail = $notification->toMail($user);
        $this->assertContains('012345', $mail->introLines);
        $this->assertStringContainsString('10 minutes', implode(' ', $mail->introLines));
        $this->assertNull($mail->actionUrl);
    }

    private function registration(string $role = 'buyer'): array
    {
        return [
            'first_name' => 'Test', 'last_name' => 'Shopper', 'role' => $role,
            'email' => $role === 'buyer' ? 'shopper@example.com' : $role.'@example.com',
            'password' => 'strong-password', 'password_confirmation' => 'strong-password',
            'phone' => '09170000000',
        ];
    }

    private function unverifiedUser(): User
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['role' => 'buyer', 'status' => 'active']);
        $user->sendEmailVerificationNotification();
        $this->actingAs($user);

        return $user;
    }

    private function codeFor(User $user): string
    {
        Notification::assertSentTo($user, VerifyEmailCode::class);

        return Notification::sent($user, VerifyEmailCode::class)->last()->code;
    }
}
