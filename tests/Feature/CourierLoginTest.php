<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Couriers (pickup drivers) sign up like riders and sign in on the website (Courier card) or in the app (the shared
 * rider and courier sign-in). Riders still sign in only in the app.
 */
class CourierLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_couriers_sign_in_on_the_website_and_in_the_app(): void
    {
        $courier = $this->driver('courier', 'courier@example.com');

        // The sign-in panel has a Courier card, and courier pages send guests to it.
        $this->get(route('login'))->assertOk()->assertSee(route('courier.login'), false)->assertSee('Courier');
        $this->get(route('courier.login'))->assertOk()->assertSee('Welcome, Courier')->assertSee(route('courier.login.submit'), false);
        $this->get('/courier/dashboard')->assertRedirect(route('courier.login'));

        $this->post(route('courier.login.submit'), ['email' => 'courier@example.com', 'password' => 'Password123!'])
            ->assertRedirect(route('courier.dashboard'));
        $this->assertAuthenticatedAs($courier);
        $this->get(route('courier.dashboard'))->assertOk();
        auth()->logout();

        // The shop sign-in points couriers to their own page.
        $this->post(route('login'), ['email' => 'courier@example.com', 'password' => 'Password123!'])
            ->assertSessionHasErrors(['email' => "This is a courier account, so it can't sign in here. Please use the Courier sign-in page instead."]);
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
            ->assertSessionHasErrors(['email' => "This is a rider account, so it can't sign in here. Please use the ZAYLO app page instead."]);
        $this->assertGuest();

        $this->postJson('/api/mobile/courier/login', ['email' => 'rider@example.com', 'password' => 'Password123!', 'device_name' => 'Test phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This sign-in is for courier accounts. Riders can use Rider sign-in instead.']);

        $this->driver('courier', 'pending@example.com', 'pending');
        $this->post(route('courier.login.submit'), ['email' => 'pending@example.com', 'password' => 'Password123!'])
            ->assertSessionHasErrors(['email' => 'This courier account is pending approval or has been suspended.']);
        $this->assertGuest();
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
