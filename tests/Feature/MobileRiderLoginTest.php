<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileRiderLoginTest extends TestCase
{
    use RefreshDatabase;

    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'];
    }

    public function test_active_verified_rider_receives_a_token(): void
    {
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);

        $this->postJson('/api/mobile/rider/login', $this->credentials($rider))
            ->assertOk()
            ->assertJsonPath('user.id', $rider->id)
            ->assertJsonStructure(['token']);
    }

    public function test_rider_login_rejects_buyers_and_buyer_login_rejects_riders(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);

        $this->postJson('/api/mobile/rider/login', $this->credentials($buyer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/mobile/login', $this->credentials($rider))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_rider_login_rejects_wrong_password_pending_and_unverified_riders(): void
    {
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $this->postJson('/api/mobile/rider/login', ['email' => $rider->email, 'password' => 'wrong'])
            ->assertUnprocessable();

        $pending = User::factory()->create(['role' => 'rider', 'status' => 'pending']);
        $this->postJson('/api/mobile/rider/login', $this->credentials($pending))
            ->assertUnprocessable();

        $unverified = User::factory()->unverified()->create(['role' => 'rider', 'status' => 'active']);
        $this->postJson('/api/mobile/rider/login', $this->credentials($unverified))
            ->assertForbidden()
            ->assertJsonPath('code', 'unverified');
    }
}
