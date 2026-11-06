<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequests;
use App\Models\User;
use App\Support\AuthModal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Sign-in and sign-up open in a modal over the storefront instead of on their own pages. */
class AuthModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_direct_visit_shows_the_home_page_with_the_panel_open(): void
    {
        foreach (['login' => 'Welcome Back', 'seller.login' => 'Welcome, Seller', 'logistics.login' => 'Welcome, Partner', 'admin.login' => 'Welcome, Admin', 'register' => 'Create new account', 'password.request' => 'Forgot your password?'] as $route => $heading) {
            $this->get(route($route))->assertOk()
                ->assertHeader('Vary', AuthModal::HEADER)
                ->assertSee('data-auth-modal', false)
                ->assertSee('New Arrivals')
                ->assertSee($heading)
                ->assertSee('js/auth-modal.js', false);
        }
        $this->assertMatchesRegularExpression('/<dialog[^>]*data-auth-modal[^>]*\sopen\s/s', $this->get(route('login'))->getContent());
    }

    public function test_the_modal_fetches_just_the_panel(): void
    {
        $panel = $this->withHeader(AuthModal::HEADER, '1')->get(route('register', ['role' => 'seller']))->assertOk()
            ->assertSee('data-auth-panel="register"', false)
            ->assertSee('Sell on ZAYLO')
            ->assertDontSee('<html', false)
            ->assertDontSee('New Arrivals');
        $this->assertStringNotContainsString('<dialog', $panel->getContent());
    }

    public function test_the_home_page_has_the_modal_for_guests_only(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('data-auth-modal', false);
        $this->get(route('products.index'))->assertOk()->assertSee('data-auth-modal', false);

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('products.index'))->assertOk()->assertDontSee('data-auth-modal', false);
    }

    public function test_signing_in_from_the_modal_returns_to_the_page_behind_it(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // six sign-ins in a row
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'password' => 'BuyerPass123!']);
        $credentials = ['email' => $buyer->email, 'password' => 'BuyerPass123!'];

        $this->postJson(route('login'), $credentials + ['return_to' => '/products?search=shoes'])
            ->assertOk()->assertExactJson(['redirect' => url('/products?search=shoes')]);
        $this->assertAuthenticatedAs($buyer);
        $this->post(route('logout'));

        // Never another site or a sign-in form; those fall back to the account page.
        foreach (['//evil.example.com/path', 'https://evil.example.com', '/\\evil.example.com', '/login', '/register?role=seller'] as $unsafe) {
            $this->postJson(route('login'), $credentials + ['return_to' => $unsafe])
                ->assertOk()->assertExactJson(['redirect' => route('buyer.dashboard')]);
            $this->post(route('logout'));
        }
    }

    public function test_modal_errors_come_back_as_json_with_the_right_portal(): void
    {
        $this->postJson(route('login'), ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'Incorrect email or password.');

        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'password' => 'SellerPass123!']);
        $this->postJson(route('login'), ['email' => $seller->email, 'password' => 'SellerPass123!'])
            ->assertStatus(422)
            ->assertJsonPath('hint.url', route('seller.login'))
            ->assertJsonValidationErrors('email');
        $this->assertGuest();
        // The link is in the response, not left behind in the session for the next page.
        $this->assertNull(session('portal_hint'));
    }

    public function test_a_modal_sign_up_moves_on_to_the_email_code_panel(): void
    {
        Notification::fake();
        $this->postJson(route('register'), [
            'first_name' => 'Test', 'last_name' => 'Shopper', 'role' => 'buyer', 'email' => 'modal@example.com',
            'password' => 'Strong-password1!', 'password_confirmation' => 'Strong-password1!',
            'phone' => '09170000011', 'date_of_birth' => '2000-09-15', 'terms' => '1',
        ])->assertOk()->assertExactJson(['redirect' => route('verification.notice')]);

        $this->withHeader(AuthModal::HEADER, '1')->get(route('verification.notice'))->assertOk()
            ->assertSee('data-auth-panel="verify"', false)
            ->assertSee('modal@example.com')
            ->assertSee('Your verification code has been sent.');

        $this->postJson(route('register'), ['role' => 'buyer'])->assertStatus(422)->assertJsonValidationErrors(['first_name', 'email', 'password']);
    }
}
