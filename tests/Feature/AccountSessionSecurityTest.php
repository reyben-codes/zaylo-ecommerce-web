<?php

namespace Tests\Feature;

use App\Models\MobileToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'New-safe-password1!';

    private function buyer(): User
    {
        return User::factory()->create(['role' => 'buyer', 'status' => 'active']);
    }

    private function browser(): void
    {
        $this->withoutToken();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    public function test_web_password_change_revokes_mobile_tokens_and_keeps_the_current_browser(): void
    {
        $buyer = $this->buyer();
        $token = $buyer->createToken('Old phone');
        $unrelated = $this->buyer()->createToken('Another account');
        $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])->assertRedirect();
        $oldSession = session()->all();
        $this->put(route('buyer.account.password'), [
            'current_password' => 'password', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasNoErrors();
        $this->get(route('buyer.account'))->assertOk();
        $this->assertNull(MobileToken::findForBearer($token));
        $this->assertNotNull(MobileToken::findForBearer($unrelated));

        // A separate browser still presenting the pre-change session must sign in again.
        $this->browser();
        $this->withSession($oldSession)->get(route('buyer.account'))->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/mobile/me')->assertUnauthorized();
    }

    public function test_reset_link_revokes_mobile_tokens_and_existing_browser_sessions(): void
    {
        $buyer = $this->buyer();
        $token = $buyer->createToken('Old phone');
        $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])->assertRedirect();
        $oldSession = session()->all();
        Auth::guard('web')->logout();
        $this->flushSession();
        $this->browser();
        $resetToken = Password::createToken($buyer);
        $this->post(route('password.update'), [
            'email' => $buyer->email, 'token' => $resetToken,
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $buyer->fresh()->password));
        $this->assertNull(MobileToken::findForBearer($token));
        $this->browser();
        $this->withSession($oldSession)->get(route('buyer.account'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_mobile_password_change_keeps_this_phone_and_invalidates_other_devices(): void
    {
        $buyer = $this->buyer();
        $current = $buyer->createToken('Current phone');
        $other = $buyer->createToken('Other phone');
        $this->post('/login', ['email' => $buyer->email, 'password' => 'password']);
        $oldSession = session()->all();
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->putJson('/api/mobile/account/password', [
            'current_password' => 'password', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();
        $this->assertNotNull(MobileToken::findForBearer($current));
        $this->assertNull(MobileToken::findForBearer($other));
        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/mobile/me')->assertOk();
        $this->browser();
        $this->withSession($oldSession)->get(route('buyer.account'))->assertRedirect(route('login'));
    }

    public function test_an_incorrect_current_password_does_not_revoke_sessions(): void
    {
        $buyer = $this->buyer();
        $token = $buyer->createToken('Phone');
        $this->actingAs($buyer)->put(route('buyer.account.password'), [
            'current_password' => 'wrong', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('current_password');
        $this->assertNotNull(MobileToken::findForBearer($token));
        $this->assertTrue(Hash::check('password', $buyer->fresh()->password));
    }

    public function test_sessions_from_before_password_protection_require_one_new_sign_in(): void
    {
        $buyer = $this->buyer();
        $this->withSession([Auth::guard('web')->getName() => $buyer->id])
            ->get(route('buyer.account'))->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->browser();
        $this->post('/login', ['email' => $buyer->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('buyer.account'))->assertOk();
    }
}
