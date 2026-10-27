<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleMobileLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT_CLIENT_ID = '71918808884-d4uh5997uq3pe2n7m4vha15ikt52vvbq.apps.googleusercontent.com';
    private const ANDROID_CLIENT_ID = '71918808884-androidclientid12345.apps.googleusercontent.com';
    private const OTHER_CLIENT_ID = '99999999999-unrelatedclientid.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => self::PROJECT_CLIENT_ID,
            'services.google.mobile_client_ids' => '71918808884-explicitmobile.apps.googleusercontent.com',
        ]);
    }

    public function test_verifier_matches_web_client_android_azp_and_same_project_ids(): void
    {
        $verifier = new GoogleIdTokenVerifier;

        // Exact web client match
        $this->assertTrue($verifier->matchesAudience(['aud' => self::PROJECT_CLIENT_ID]));

        // Web client in azp with android client in aud
        $this->assertTrue($verifier->matchesAudience([
            'aud' => self::ANDROID_CLIENT_ID,
            'azp' => self::PROJECT_CLIENT_ID,
        ]));

        // Same project prefix match in aud
        $this->assertTrue($verifier->matchesAudience(['aud' => self::ANDROID_CLIENT_ID]));

        // Same project prefix match in azp
        $this->assertTrue($verifier->matchesAudience([
            'aud' => 'some-audience',
            'azp' => self::ANDROID_CLIENT_ID,
        ]));

        // Array in aud
        $this->assertTrue($verifier->matchesAudience(['aud' => [self::PROJECT_CLIENT_ID, 'other']]));

        // Explicit mobile_client_ids match
        $this->assertTrue($verifier->matchesAudience(['aud' => '71918808884-explicitmobile.apps.googleusercontent.com']));

        // Foreign project token rejected
        $this->assertFalse($verifier->matchesAudience(['aud' => self::OTHER_CLIENT_ID]));
        $this->assertFalse($verifier->matchesAudience(['aud' => self::OTHER_CLIENT_ID, 'azp' => self::OTHER_CLIENT_ID]));

        // A longer project number that only starts with ours is a different project.
        $this->assertFalse($verifier->matchesAudience(['aud' => '719188088841-lookalike.apps.googleusercontent.com']));
        // Lookalike domains are rejected.
        $this->assertFalse($verifier->matchesAudience(['aud' => '71918808884-evil.apps.googleusercontent.com.attacker.example']));
    }

    public function test_a_mistyped_client_id_setting_does_not_accept_other_projects(): void
    {
        config(['services.google.client_id' => 'a-typo', 'services.google.mobile_client_ids' => '']);

        $this->assertFalse((new GoogleIdTokenVerifier)->matchesAudience(['aud' => 'a-anything.apps.googleusercontent.com']));
        $this->assertTrue((new GoogleIdTokenVerifier)->matchesAudience(['aud' => 'a-typo']));
    }

    public function test_buyer_can_sign_in_using_alternative_token_parameter_names(): void
    {
        $claims = [
            'aud' => self::ANDROID_CLIENT_ID,
            'azp' => self::PROJECT_CLIENT_ID,
            'iss' => 'https://accounts.google.com',
            'exp' => (string) now()->addHour()->timestamp,
            'sub' => 'google-buyer-mobile-1',
            'email' => 'mobilebuyer@example.com',
            'email_verified' => 'true',
            'name' => 'Mobile Buyer',
        ];

        Http::fake([
            GoogleIdTokenVerifier::ENDPOINT.'*' => Http::response($claims, 200),
        ]);

        // 1. Using idToken (camelCase as sent by some Flutter/JS plugins)
        $response = $this->postJson('/api/mobile/google', [
            'idToken' => 'valid-token-1',
            'device_name' => 'Samsung Galaxy S24',
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('mobilebuyer@example.com', $response->json('user.email'));

        // 2. Using credential (from Google Credential Manager)
        $response2 = $this->postJson('/api/mobile/google', [
            'credential' => 'valid-token-2',
            'device_name' => 'Pixel 9',
        ])->assertOk();

        $this->assertNotEmpty($response2->json('token'));
        $this->assertSame('mobilebuyer@example.com', $response2->json('user.email'));

        // 3. Using token
        $response3 = $this->postJson('/api/mobile/google', [
            'token' => 'valid-token-3',
        ])->assertOk();

        $this->assertNotEmpty($response3->json('token'));
    }

    public function test_rider_can_sign_in_with_id_token_or_id_token_camel_case(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Logistics', 'slug' => 'express', 'status' => 'approved']);
        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'email' => 'rider@example.com', 'email_verified_at' => now()]);
        Rider::create(['user_id' => $riderUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);

        $claims = [
            'aud' => self::ANDROID_CLIENT_ID,
            'iss' => 'https://accounts.google.com',
            'exp' => (string) now()->addHour()->timestamp,
            'sub' => 'google-rider-mobile-1',
            'email' => 'rider@example.com',
            'email_verified' => 'true',
            'name' => 'Mobile Rider',
        ];

        Http::fake([
            GoogleIdTokenVerifier::ENDPOINT.'*' => Http::response($claims, 200),
        ]);

        $response = $this->postJson('/api/mobile/rider/google', [
            'idToken' => 'valid-rider-token',
            'device_name' => 'Rider Phone',
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('rider@example.com', $response->json('user.email'));
    }
}
