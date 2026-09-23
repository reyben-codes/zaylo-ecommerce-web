<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_has_a_dedicated_sign_in_screen(): void
    {
        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.login'));

        $this->get(route('seller.login'))
            ->assertOk()
            ->assertSee('ZAYLO SELLER CENTER')
            ->assertSee('Welcome, Seller')
            ->assertSee('action="'.route('seller.login.submit').'"', false)
            ->assertSee(route('register', ['role' => 'seller']))
            ->assertSee(route('login'));

        $this->get(route('login'))->assertOk()->assertSee(route('seller.login'));
    }

    public function test_active_seller_can_sign_in_and_logout_through_seller_center(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'status' => 'active',
            'password' => 'SellerPass123!',
        ]);

        $this->post(route('seller.login.submit'), [
            'email' => $seller->email,
            'password' => 'SellerPass123!',
            'remember' => '1',
        ])->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($seller);

        $this->post(route('logout'))->assertRedirect(route('seller.login'));
        $this->assertGuest();
    }

    public function test_buyer_credentials_cannot_enter_seller_center(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'status' => 'active',
            'password' => 'BuyerPass123!',
        ]);

        $this->from(route('seller.login'))->post(route('seller.login.submit'), [
            'email' => $buyer->email,
            'password' => 'BuyerPass123!',
        ])->assertRedirect(route('seller.login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_pending_seller_cannot_enter_seller_center(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'status' => 'pending',
            'password' => 'SellerPass123!',
        ]);

        $this->from(route('seller.login'))->post(route('seller.login.submit'), [
            'email' => $seller->email,
            'password' => 'SellerPass123!',
        ])->assertRedirect(route('seller.login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
