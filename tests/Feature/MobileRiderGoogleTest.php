<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * "Continue with Google" in the rider app: it signs in riders who already applied (linking Google to the account
 * with the same email) and never creates an account, since riders must apply with their documents first.
 */
class MobileRiderGoogleTest extends TestCase
{
    use RefreshDatabase;
    use SignsInMobile;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'web-client.apps.googleusercontent.com']);
        $claims = fn (string $sub, string $email, array $overrides = []) => $overrides + [
            'aud' => 'web-client.apps.googleusercontent.com', 'iss' => 'https://accounts.google.com',
            'exp' => (string) now()->addHour()->timestamp, 'sub' => $sub, 'email' => $email,
            'email_verified' => 'true', 'name' => 'Google Name', 'picture' => 'https://lh3.googleusercontent.com/a/photo',
        ];
        $tokens = [
            'rider' => $claims('2001', 'jun.rider@example.com'),
            'stranger' => $claims('2002', 'nobody@example.com'),
            'buyer' => $claims('2003', 'shopper@example.com'),
            'applicant' => $claims('2004', 'waiting.rider@example.com'),
            'unverified' => $claims('2005', 'jun.rider@example.com', ['email_verified' => 'false']),
        ];
        Http::fake(fn (\Illuminate\Http\Client\Request $request) => isset($tokens[$request['id_token']])
            ? Http::response($tokens[$request['id_token']])
            : Http::response(['error' => 'invalid_token'], 400));
    }

    public function test_an_approved_rider_signs_in_with_the_google_account_of_the_same_email(): void
    {
        $rider = $this->approvedRider(['email' => 'jun.rider@example.com', 'name' => 'Jun Reyes']);

        $response = $this->postJson('/api/mobile/rider/google', ['id_token' => 'rider', 'device_name' => 'Pixel'])
            ->assertOk()->assertJsonPath('user.id', $rider->id)->assertJsonPath('user.role', 'rider');
        $this->assertSame('2001', $rider->fresh()->google_id);
        $this->assertSame('Jun Reyes', $rider->fresh()->name);
        $this->assertTrue(ActivityLog::where('user_id', $rider->id)->where('event', 'mobile.login')->exists());

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))->getJson('/api/mobile/rider/dashboard')->assertOk();

        // Next time the Google ID alone finds the account.
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->postJson('/api/mobile/rider/google', ['id_token' => 'rider'])
            ->assertOk()->assertJsonPath('user.id', $rider->id);
        $this->assertSame(1, User::where('email', 'jun.rider@example.com')->count());
    }

    public function test_google_never_creates_a_rider_or_signs_in_other_accounts(): void
    {
        $this->postJson('/api/mobile/rider/google', ['id_token' => 'stranger'])->assertUnprocessable()
            ->assertJsonPath('errors.google.0', 'No rider account uses this Google email. Register as a rider first, or sign in with your email and password.');
        $this->assertFalse(User::where('email', 'nobody@example.com')->exists());

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email' => 'shopper@example.com']);
        $this->postJson('/api/mobile/rider/google', ['id_token' => 'buyer'])->assertUnprocessable()
            ->assertJsonPath('errors.google.0', 'This Google email belongs to a buyer account. Use Log in as a buyer instead.');
        $this->assertNull($buyer->fresh()->google_id);
        $this->assertSame(0, $buyer->tokens()->count());

        // An application still in review gets the same answer as with a password.
        $applicant = User::factory()->create(['role' => 'rider', 'status' => 'pending', 'email' => 'waiting.rider@example.com']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Agila Express', 'slug' => 'agila', 'status' => 'approved']);
        Rider::create(['user_id' => $applicant->id, 'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle', 'status' => 'pending']);
        $this->postJson('/api/mobile/rider/google', ['id_token' => 'applicant'])->assertUnprocessable()
            ->assertJsonPath('errors.google.0', 'Your rider application is still being reviewed by Agila Express.');
        $this->assertSame(0, $applicant->tokens()->count());

        // Unverified Google emails and forged tokens are refused.
        $this->approvedRider(['email' => 'jun.rider@example.com']);
        foreach (['unverified', 'forged'] as $token) {
            $this->postJson('/api/mobile/rider/google', ['id_token' => $token])->assertUnprocessable()->assertJsonValidationErrors('google');
        }
    }

    public function test_without_a_google_client_id_the_app_is_told_to_use_a_password(): void
    {
        config(['services.google.client_id' => null]);
        $this->postJson('/api/mobile/rider/google', ['id_token' => 'rider'])
            ->assertStatus(503)->assertJsonPath('code', 'google_unconfigured');
    }
}
