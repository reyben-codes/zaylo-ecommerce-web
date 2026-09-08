<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $googleHttp;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => [
            'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'redirect' => 'http://localhost/auth/google/callback',
        ]]);
        Notification::fake();
        $this->googleHttp = new MockHandler;
        // Use the actual Socialite Google provider/state validation, replacing only HTTP.
        Socialite::shouldReceive('driver')->with('google')->andReturnUsing(function () {
            return (new GoogleProvider(request(), 'test-client', 'test-secret', 'http://localhost/auth/google/callback'))
                ->setHttpClient(new Client(['handler' => HandlerStack::create($this->googleHttp)]));
        });
    }

    protected function tearDown(): void
    {
        Notification::assertNothingSent();
        parent::tearDown();
    }

    public function test_new_buyer_is_verified_active_and_has_no_password_or_otp(): void
    {
        Event::fake([Verified::class]);
        $state = $this->begin();
        $oldSession = session()->getId();
        $this->identity();
        $this->completeGoogle($state)->assertRedirect(route('buyer.dashboard'))->assertSessionMissing('google_oauth')->assertSessionMissing('state');
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-sub-123', $user->google_id);
        $this->assertSame('Google Shopper', $user->name);
        $this->assertSame('google', $user->auth_provider);
        $this->assertSame('buyer', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNull($user->password);
        $this->assertNull($user->approved_at);
        $this->assertNull($user->approved_by);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertStringNotContainsString('mock-access-token', serialize(session()->all()));
        $this->assertDatabaseCount('email_verification_codes', 0);
        $this->get(route('verification.notice'))->assertRedirect(route('buyer.dashboard'));
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_new_sellers_and_couriers_still_need_existing_admin_approval(): void
    {
        foreach (['seller', 'courier'] as $role) {
            $this->travel(61)->seconds();
            $state = $this->begin($role);
            $this->identity(['sub' => 'google-'.$role, 'email' => $role.'@gmail.com']);
            $this->completeGoogle($state, ['role' => 'admin'])->assertRedirect(route('login'))->assertSessionHasErrors('google');
            $user = User::where('email', $role.'@gmail.com')->firstOrFail();
            $this->assertGuest();
            $this->assertSame($role, $user->role);
            $this->assertSame('pending', $user->status);
            $this->assertNotNull($user->email_verified_at);
            $this->assertNull($user->approved_at);
            $this->assertNull($user->approved_by);
            $this->assertDatabaseHas($role.'_profiles', ['user_id' => $user->id]);

            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
            $this->actingAs($admin)->patch(route('admin.users.status', $user), ['status' => 'active'])->assertSessionHasNoErrors();
            $this->post(route('logout'));
            $state = $this->begin('buyer');
            $this->identity(['sub' => 'google-'.$role, 'email' => $role.'@gmail.com']);
            $this->completeGoogle($state)->assertRedirect(route($role.'.dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->assertSame($admin->id, $user->fresh()->approved_by);
            $this->post(route('logout'));
        }
    }

    public function test_invalid_public_roles_are_rejected_before_redirect(): void
    {
        foreach (['admin', 'sorting_center', 'unknown', ['seller']] as $role) {
            $this->get(route('google.redirect', ['role' => $role]))
                ->assertRedirect(route('login'))->assertSessionHasErrors('google')->assertSessionMissing('google_oauth');
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_callback_cannot_override_the_session_role(): void
    {
        $state = $this->begin('buyer');
        $this->identity();
        $this->completeGoogle($state, ['role' => 'sorting_center'])->assertRedirect(route('buyer.dashboard'));
        $this->assertSame('buyer', User::sole()->role);
    }

    public function test_local_account_is_linked_without_changing_password_role_or_data(): void
    {
        $local = User::factory()->unverified()->create([
            'email' => 'shopper@gmail.com', 'name' => 'Local Name', 'role' => 'seller', 'status' => 'active',
            'phone' => '09170000000', 'address' => 'Legacy address', 'approved_at' => now(),
        ]);
        DB::table('seller_profiles')->insert(['user_id' => $local->id, 'store_name' => 'Existing Store', 'created_at' => now(), 'updated_at' => now()]);
        $cart = $local->cart()->create();
        $address = $local->addresses()->create([
            'recipient_name' => 'Local Name', 'phone' => '09170000000', 'line1' => 'Saved street',
            'city' => 'Manila', 'province' => '', 'label' => 'Home',
        ]);
        $password = $local->getRawOriginal('password');
        $approval = $local->approved_at->toDateTimeString();
        $state = $this->begin('courier');
        $this->identity();
        $this->completeGoogle($state)->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($local);
        $local->refresh();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($password, $local->getRawOriginal('password'));
        $this->assertSame('local', $local->auth_provider);
        $this->assertSame('seller', $local->role);
        $this->assertSame('Local Name', $local->name);
        $this->assertSame($approval, $local->approved_at->toDateTimeString());
        $this->assertSame('Legacy address', $local->address);
        $this->assertSame($cart->id, $local->cart->id);
        $this->assertSame($address->id, $local->addresses()->sole()->id);
        $this->assertDatabaseHas('seller_profiles', ['user_id' => $local->id, 'store_name' => 'Existing Store']);
        $this->assertDatabaseCount('courier_profiles', 0);
        $this->assertSame('https://lh3.googleusercontent.com/test-avatar', $local->avatar);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $local->email, 'password' => 'password'])->assertRedirect(route('seller.dashboard'));
    }

    public function test_returning_google_id_reuses_account_even_if_google_email_changes(): void
    {
        $user = User::factory()->create(['email' => 'original@gmail.com', 'role' => 'buyer', 'status' => 'active']);
        $user->forceFill(['google_id' => 'google-sub-123', 'auth_provider' => 'google', 'password' => null])->save();
        $state = $this->begin('courier');
        $this->identity(['email' => 'changed@gmail.com']);
        $this->completeGoogle($state)->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('original@gmail.com', $user->fresh()->email);
        $this->assertSame('buyer', $user->fresh()->role);
    }

    public function test_unverified_missing_or_invalid_google_identity_is_rejected(): void
    {
        $local = User::factory()->create(['email' => 'shopper@gmail.com', 'role' => 'buyer', 'status' => 'active']);
        foreach ([
            ['email_verified' => false], ['email_verified' => null], ['email_verified' => 'false'],
            ['email' => null], ['email' => 'not-an-email'], ['sub' => null],
        ] as $override) {
            $this->travel(61)->seconds();
            $state = $this->begin();
            $this->identity($override);
            $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
            $this->assertGuest();
            $this->assertNull($local->fresh()->google_id);
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_cancelled_and_failed_oauth_are_graceful_and_do_not_leak_errors(): void
    {
        $state = $this->begin();
        $this->completeGoogle($state, ['error' => 'access_denied', 'error_description' => 'private-provider-message'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('google')->assertSessionMissing('state');
        $this->get(route('login'))->assertSee('cancelled or denied')->assertDontSee('private-provider-message');

        $state = $this->begin();
        $this->googleHttp->append(new ConnectException('secret-token-value', new GuzzleRequest('POST', 'https://google.invalid/token')));
        $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->get(route('login'))->assertDontSee('secret-token-value')->assertDontSee('test-secret');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_missing_expired_and_replayed_state_are_rejected(): void
    {
        $state = $this->begin();
        $this->identity();
        $this->completeGoogle('wrong-state')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertSame(2, $this->googleHttp->count(), 'Invalid state must not reach the token endpoint.');
        $this->googleHttp->reset();
        $this->assertGuest();
        $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->travel(61)->seconds();
        $state = $this->begin();
        $this->travel(11)->minutes();
        $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0);

        $state = $this->begin();
        $this->identity();
        $this->completeGoogle($state)->assertRedirect(route('buyer.dashboard'));
        $this->post(route('logout'));
        $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_missing_session_state_is_rejected_before_token_exchange(): void
    {
        $state = $this->begin();
        session()->forget('state');
        $this->identity();
        $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertSame(2, $this->googleHttp->count());
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_conflicting_google_links_are_rejected_without_merging_accounts(): void
    {
        $first = User::factory()->create(['email' => 'shopper@gmail.com', 'role' => 'buyer', 'status' => 'active']);
        $first->forceFill(['google_id' => 'other-google-id'])->save();
        $state = $this->begin();
        $this->identity();
        $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertSame('other-google-id', $first->fresh()->google_id);

        $second = User::factory()->create(['email' => 'second@gmail.com', 'role' => 'buyer', 'status' => 'active']);
        $second->forceFill(['google_id' => 'google-sub-123'])->save();
        $state = $this->begin();
        $this->identity();
        $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertSame('second@gmail.com', $second->fresh()->email);
        $this->assertDatabaseCount('users', 2);
        $this->assertGuest();
    }

    public function test_suspended_accounts_are_never_logged_in_or_reactivated(): void
    {
        $user = User::factory()->create(['email' => 'shopper@gmail.com', 'role' => 'buyer', 'status' => 'suspended']);
        $state = $this->begin();
        $this->identity();
        $this->completeGoogle($state)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertSame('suspended', $user->fresh()->status);
    }

    public function test_existing_admin_role_is_preserved_not_publicly_created(): void
    {
        $admin = User::factory()->create(['email' => 'shopper@gmail.com', 'role' => 'admin', 'status' => 'active']);
        $state = $this->begin('buyer');
        $this->identity();
        $this->completeGoogle($state)->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_unconfigured_google_is_safe_and_both_pages_show_the_button(): void
    {
        config(['services.google.client_id' => null]);
        $this->get(route('google.redirect'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->get(route('login'))->assertOk()->assertSee('Continue with Google')->assertSee(route('google.redirect'));
        $this->get(route('register', ['role' => 'courier']))->assertOk()->assertSee(route('google.redirect', ['role' => 'courier']));
        $this->assertGuest();
    }

    private function begin(string $role = 'buyer'): string
    {
        $response = $this->get(route('google.redirect', ['role' => $role]))->assertRedirect();
        $this->assertSame('accounts.google.com', parse_url($response->headers->get('Location'), PHP_URL_HOST));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('http://localhost/auth/google/callback', $query['redirect_uri']);
        $this->assertSame($role, session('google_oauth.role'));
        $this->assertSame(session('state'), $query['state']);

        return $query['state'];
    }

    private function identity(array $overrides = []): void
    {
        $this->googleHttp->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'mock-access-token', 'expires_in' => 3600, 'token_type' => 'Bearer'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(array_replace([
                'sub' => 'google-sub-123', 'name' => 'Google Shopper', 'email' => 'shopper@gmail.com',
                'email_verified' => true, 'picture' => 'https://lh3.googleusercontent.com/test-avatar',
            ], $overrides)))
        );
    }

    private function completeGoogle(string $state, array $extra = [])
    {
        return $this->get(route('google.callback', array_replace(['state' => $state, 'code' => 'mock-code'], $extra)));
    }
}
