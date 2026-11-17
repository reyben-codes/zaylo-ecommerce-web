<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

/**
 * Couriers sign up on the website and sign in only in the app.
 */
class CourierLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_couriers_sign_in_only_in_the_app(): void
    {
        $this->driver('courier', 'courier@example.com');

        $this->get(route('login'))->assertOk()->assertDontSee('data-portal="courier"', false)
            ->assertDontSee(route('courier.login'), false);
        $this->get(route('courier.login'))->assertRedirect(route('courier.app'));
        $this->get(route('courier.app'))->assertOk()->assertSee('ZAYLO Couriers');
        $this->get(route('courier.app'), ['User-Agent' => 'Android'])->assertOk();
        $this->get('/courier/dashboard')->assertRedirect(route('courier.app'));

        $this->post(route('courier.login.submit'), ['email' => 'courier@example.com', 'password' => 'Password123!'])
            ->assertRedirect(route('courier.app'));
        $this->assertGuest();

        // The shop sign-in points couriers to the app.
        $this->post(route('login'), ['email' => 'courier@example.com', 'password' => 'Password123!'])
            ->assertSessionHasErrors(['email' => "This is a courier account, so it can't sign in here. Please use the ZAYLO app page instead."]);
        $this->assertGuest();

        // Mobile app sign-in: courier and rider endpoints are separated.
        $this->postJson('/api/mobile/courier/login', ['email' => 'courier@example.com', 'password' => 'Password123!', 'device_name' => 'Test phone'])
            ->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/mobile/rider/login', ['email' => 'courier@example.com', 'password' => 'Password123!', 'device_name' => 'Test phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This sign-in is for rider accounts. Couriers can use Courier sign-in instead.']);
    }

    public function test_riders_and_couriers_waiting_for_approval_cannot_use_the_courier_sign_in(): void
    {
        $this->driver('rider', 'rider@example.com');
        $this->post(route('courier.login.submit'), ['email' => 'rider@example.com', 'password' => 'Password123!'])
            ->assertRedirect(route('courier.app'));
        $this->assertGuest();

        $this->postJson('/api/mobile/courier/login', ['email' => 'rider@example.com', 'password' => 'Password123!', 'device_name' => 'Test phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This sign-in is for courier accounts. Riders can use Rider sign-in instead.']);

        $this->driver('courier', 'pending@example.com', 'pending');
        $this->post(route('courier.login.submit'), ['email' => 'pending@example.com', 'password' => 'Password123!'])
            ->assertRedirect(route('courier.app'));
        $this->assertGuest();
    }

    public function test_courier_google_sign_in_is_available_only_on_mobile(): void
    {
        config([
            'services.google.client_id' => 'web-client.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $courier = $this->driver('courier', 'courier.google@example.com');

        $this->get(route('google.redirect', ['role' => 'courier']))->assertRedirect(route('courier.app'));

        // An OAuth session started before web sign-in was removed cannot sign in either.
        Socialite::shouldReceive('driver')->never();

        session()->put('google_oauth', ['role' => 'courier', 'started_at' => now()->timestamp]);
        $this->get('/auth/google/callback?state=xyz&code=abc')
            ->assertRedirect(route('courier.app'));
        $this->assertGuest();

        // Mobile Google token sign-in
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'web-client.apps.googleusercontent.com',
                'iss' => 'https://accounts.google.com',
                'exp' => (string) now()->addHour()->timestamp,
                'sub' => 'google-courier-123',
                'email' => 'courier.google@example.com',
                'email_verified' => 'true',
                'name' => 'Courier Google',
            ]),
        ]);

        $this->postJson('/api/mobile/courier/google', ['id_token' => 'valid-token', 'device_name' => 'Courier Phone'])
            ->assertOk()->assertJsonStructure(['token', 'user']);
        $this->assertSame('google-courier-123', $courier->fresh()->google_id);
    }

    private function driver(string $role, string $email, string $status = 'active'): User
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $partner = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express '.$email, 'slug' => 'express-'.md5($email), 'status' => 'approved']);
        $user = User::factory()->create(['role' => $role, 'status' => $status, 'email' => $email, 'password' => 'Password123!', 'email_verified_at' => now()]);
        Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $partner->id, 'vehicle_type' => 'Motorcycle', 'status' => $status === 'active' ? 'approved' : 'pending', 'is_active' => true]);

        return $user;
    }
}
