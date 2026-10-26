<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shoppers on Android download the app; the web store stays for computers, iPhones and business accounts. */
class RequireAndroidAppTest extends TestCase
{
    use RefreshDatabase;

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

    private function as(string $agent): static
    {
        return $this->withHeader('User-Agent', $agent);
    }

    public function test_android_shoppers_are_sent_to_download_the_app(): void
    {
        foreach (['/', '/products', '/login', '/register', '/register?role=courier', '/rider/login'] as $page) {
            $this->as(self::ANDROID)->get($page)->assertRedirect(route('app.download'));
        }
        // Pages that need a sign-in go to the login page first, which then sends the guest to the app.
        $this->as(self::ANDROID)->get('/buyer/cart')->assertRedirect(route('login'));

        $this->as(self::ANDROID)->get(route('app.download'))->assertOk()
            ->assertSee('Shop ZAYLO on the app')
            ->assertSee('href="'.config('marketplace.android_app.download_url').'"', false)
            ->assertSee(route('seller.login'));
    }

    public function test_computers_and_iphones_keep_the_web_store(): void
    {
        foreach ([self::DESKTOP, self::IPHONE] as $agent) {
            $this->as($agent)->get('/')->assertOk();
            $this->as($agent)->get('/products')->assertOk();
        }
    }

    public function test_business_portals_legal_pages_and_email_links_stay_open_on_android(): void
    {
        foreach (['/seller/login', '/logistics/login', '/admin/login', '/register?role=seller', '/register?role=logistics', '/terms', '/forgot-password', '/up',
            '/storage/products/7/photo.png', '/auth/google?role=seller', '/auth/google/callback'] as $page) {
            $response = $this->as(self::ANDROID)->get($page);
            $this->assertNotSame(route('app.download'), $response->headers->get('Location'), "$page should stay open");
        }
    }

    public function test_signed_in_sellers_use_the_website_on_android_but_buyers_do_not(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->as(self::ANDROID)->get('/')->assertDontSee(route('app.download'));
        $this->assertNotSame(route('app.download'), $this->actingAs($seller)->as(self::ANDROID)->get('/')->headers->get('Location'));

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->as(self::ANDROID)->get('/')->assertRedirect(route('app.download'));
    }

    public function test_form_posts_and_json_requests_are_not_redirected_and_the_gate_can_be_turned_off(): void
    {
        $this->as(self::ANDROID)->getJson('/products')->assertOk();
        $this->assertNotSame(route('app.download'), $this->as(self::ANDROID)->post('/login', [])->headers->get('Location'));

        config(['marketplace.android_app.required' => false]);
        $this->as(self::ANDROID)->get('/')->assertOk();
    }
}
