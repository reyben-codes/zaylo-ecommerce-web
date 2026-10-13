<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProvisionSellerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioned_seller_can_sign_in_on_the_seller_login_page(): void
    {
        $this->assertSame(0, Artisan::call('seller:provision', [
            'email' => 'Demo.Seller@Example.com', '--name' => 'Demo Seller', '--store' => 'Demo Boutique', '--phone' => '09171234567',
        ]));
        $password = $this->passwordFrom(Artisan::output());

        $seller = User::where('email', 'demo.seller@example.com')->firstOrFail();
        $this->assertTrue($seller->hasRole('seller'));
        $this->assertTrue($seller->hasVerifiedEmail());
        $this->assertTrue($seller->isActive());
        $this->assertSame('approved', $seller->sellerShop()->status);
        $this->assertSame('Demo Boutique', $seller->sellerShop()->name);

        $this->post(route('seller.login.submit'), ['email' => 'demo.seller@example.com', 'password' => $password])
            ->assertRedirect(route('seller.dashboard'));
        $this->get(route('seller.dashboard'))->assertOk()->assertSee('Demo Boutique');
    }

    public function test_rerunning_keeps_the_password_unless_a_reset_is_requested(): void
    {
        Artisan::call('seller:provision', ['email' => 'shop@example.com']);
        $original = $this->passwordFrom(Artisan::output());

        Artisan::call('seller:provision', ['email' => 'shop@example.com']);
        $this->assertStringNotContainsString('Password', Artisan::output());
        $this->assertTrue(auth()->validate(['email' => 'shop@example.com', 'password' => $original]));

        Artisan::call('seller:provision', ['email' => 'shop@example.com', '--reset-password' => true]);
        $replacement = $this->passwordFrom(Artisan::output());
        $this->assertNotSame($original, $replacement);
        $this->assertTrue(auth()->validate(['email' => 'shop@example.com', 'password' => $replacement]));
        $this->assertSame(1, User::where('email', 'shop@example.com')->count());
    }

    public function test_it_refuses_to_convert_a_non_seller_account(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'email' => 'buyer@example.com']);

        $this->assertSame(1, Artisan::call('seller:provision', ['email' => 'buyer@example.com', '--reset-password' => true]));
        $this->assertSame('buyer', $buyer->fresh()->role);
    }

    private function passwordFrom(string $output): string
    {
        $this->assertMatchesRegularExpression('/\|\s*Password\s*\|\s*(\S+)\s*\|/', $output);
        preg_match('/\|\s*Password\s*\|\s*(\S+)\s*\|/', $output, $matches);

        return $matches[1];
    }
}
