<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Continue with Google" in the buyer app: real RS256 ID tokens signed with a
 * test key, checked against a faked copy of Google's published keys.
 */
class MobileGoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'web-client.apps.googleusercontent.com';

    /** @var array<string, \OpenSSLAsymmetricKey> */
    private array $keys = [];

    /** What the fake Google key endpoint serves; tests change these. */
    private array $publishedKeys = ['key-1'];

    private int $certsStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => self::CLIENT_ID, 'services.google.mobile_client_ids' => 'ios-client.apps.googleusercontent.com']);
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([GoogleIdTokenVerifier::CERTS_URL => fn () => $this->certsStatus === 200
            ? Http::response($this->jwks($this->publishedKeys), 200, ['Cache-Control' => 'public, max-age=3600'])
            : Http::response('', $this->certsStatus)]);
    }

    /** Fixed test keys: generating RSA keys needs an openssl.cnf that Windows PHP often lacks. */
    private function key(string $name): \OpenSSLAsymmetricKey
    {
        return $this->keys[$name] ??= openssl_pkey_get_private(file_get_contents(base_path("tests/Fixtures/google/{$name}.pem")));
    }

    private function jwks(array $kids): array
    {
        $encode = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return ['keys' => array_map(function (string $kid) use ($encode) {
            $rsa = openssl_pkey_get_details($this->key($kid))['rsa'];

            return ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $kid, 'n' => $encode($rsa['n']), 'e' => $encode($rsa['e'])];
        }, $kids)];
    }

    private function idToken(array $overrides = [], string $kid = 'key-1'): string
    {
        return JWT::encode($overrides + [
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => 'google-sub-123',
            'email' => 'shopper@example.test',
            'email_verified' => true,
            'name' => 'Google Shopper',
            'picture' => 'https://lh3.googleusercontent.com/a/photo',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $this->key($kid), 'RS256', $kid);
    }

    private function signIn(string $token)
    {
        return $this->postJson('/api/mobile/google', ['id_token' => $token, 'device_name' => 'ZAYLO android']);
    }

    public function test_new_buyer_is_created_verified_and_signed_in(): void
    {
        $this->signIn($this->idToken())->assertOk()
            ->assertJsonPath('user.email', 'shopper@example.test')
            ->assertJsonPath('user.google_linked', true)
            ->assertJsonPath('user.has_password', false)
            ->assertJsonStructure(['token']);

        $user = User::sole();
        $this->assertSame('buyer', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertSame('google-sub-123', $user->google_id);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->getAuthPassword());
        $this->assertSame(1, $user->tokens()->count());

        // Signing in again finds the same account.
        $this->signIn($this->idToken())->assertOk();
        $this->assertSame(1, User::count());
    }

    public function test_existing_buyer_is_linked_by_exact_email_and_keeps_their_password(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email' => 'shopper@example.test', 'password' => Hash::make('Secret#123')]);

        $this->signIn($this->idToken())->assertOk()->assertJsonPath('user.id', $buyer->id)->assertJsonPath('user.has_password', true);
        $this->assertSame('google-sub-123', $buyer->fresh()->google_id);
        $this->assertTrue(Hash::check('Secret#123', $buyer->fresh()->getAuthPassword()));
    }

    public function test_ios_tokens_issued_to_the_ios_client_are_accepted(): void
    {
        $this->signIn($this->idToken(['aud' => 'ios-client.apps.googleusercontent.com']))->assertOk();
    }

    // Sign-in attempts are rate limited, so each test stays under three of them.
    public function test_tokens_for_other_apps_are_rejected(): void
    {
        $this->signIn($this->idToken(['aud' => 'someone-elses-app']))->assertUnprocessable()->assertJsonValidationErrors('google');
        $this->signIn($this->idToken(['iss' => 'https://evil.example']))->assertUnprocessable();
        $this->assertSame(0, User::count());
    }

    public function test_expired_and_unverified_email_tokens_are_rejected(): void
    {
        $this->signIn($this->idToken(['exp' => time() - 600, 'iat' => time() - 4200]))->assertUnprocessable();
        $this->signIn($this->idToken(['email_verified' => false]))->assertUnprocessable();
        $this->assertSame(0, User::count());
    }

    public function test_forged_unsigned_and_malformed_tokens_are_rejected(): void
    {
        // Signed with a key Google never published (the kid is real, the signature is not).
        $forged = JWT::encode(['iss' => 'https://accounts.google.com', 'aud' => self::CLIENT_ID, 'sub' => 'x', 'exp' => time() + 60],
            $this->key('forged'), 'RS256', 'key-1');
        $this->signIn($forged)->assertUnprocessable();
        // Unsigned and HMAC tokens are never accepted.
        $this->signIn(JWT::encode(['sub' => 'x', 'aud' => self::CLIENT_ID], str_repeat('s', 64), 'HS256', 'key-1'))->assertUnprocessable();
        $this->signIn('not-a-token')->assertUnprocessable();

        $this->assertSame(0, User::count());
    }

    public function test_seller_and_rider_accounts_are_refused_without_being_linked(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'email' => 'shopper@example.test']);

        $this->signIn($this->idToken())->assertUnprocessable()
            ->assertJsonPath('errors.google.0', 'This sign-in is for buyer accounts. Riders can use Login as a Rider; sellers can use the website.');
        $this->assertNull($seller->fresh()->google_id);
        $this->assertSame(1, User::count());
    }

    public function test_suspended_buyers_cannot_sign_in(): void
    {
        User::factory()->create(['role' => 'buyer', 'status' => 'suspended', 'email' => 'shopper@example.test']);

        $this->signIn($this->idToken())->assertUnprocessable()->assertJsonPath('errors.google.0', 'This account is suspended. Please contact support.');
    }

    public function test_rotated_google_keys_are_fetched_again(): void
    {
        $this->signIn($this->idToken())->assertOk();
        $this->publishedKeys = ['key-1', 'key-2'];
        $this->signIn($this->idToken(kid: 'key-2'))->assertOk();
        Http::assertSentCount(2);
    }

    public function test_it_explains_when_google_is_not_configured_or_unreachable(): void
    {
        config(['services.google.client_id' => null, 'services.google.mobile_client_ids' => null]);
        $this->signIn($this->idToken())->assertStatus(503)->assertJsonPath('code', 'google_unconfigured');

        config(['services.google.client_id' => self::CLIENT_ID]);
        $this->certsStatus = 500;
        $this->signIn($this->idToken())->assertStatus(503);
        $this->assertSame(0, User::count());
    }
}
