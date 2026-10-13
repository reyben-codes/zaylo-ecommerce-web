<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CompletesSignUp;
use Tests\TestCase;

/**
 * Sign-up rules from the black-box test workbook: name length, email domain, unique mobile numbers (09 or +63),
 * buyer age, numbers in passwords, Terms consent and a 5-minute verification code.
 */
class RegistrationRulesTest extends TestCase
{
    use CompletesSignUp, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_names_must_be_2_to_50_characters(): void
    {
        $attempt = 0;
        foreach (['J' => false, 'Jo' => true, str_repeat('a', 50) => true, str_repeat('a', 51) => false] as $name => $accepted) {
            $attempt++;
            $response = $this->post(route('register'), $this->registration([
                'first_name' => $name, 'last_name' => $name, 'email' => "name{$attempt}@example.com", 'phone' => "0917000000{$attempt}",
            ]));
            $accepted
                ? $response->assertSessionHasNoErrors()
                : $response->assertSessionHasErrors(['first_name' => 'The first name must be 2 to 50 characters.', 'last_name' => 'The last name must be 2 to 50 characters.']);
            $this->post(route('logout'));
        }
    }

    public function test_email_needs_a_domain_extension(): void
    {
        $this->post(route('register'), $this->registration(['email' => 'juan@example']))
            ->assertSessionHasErrors(['email' => 'Enter a valid email address, such as name@example.com.']);
        $this->assertDatabaseMissing('users', ['email' => 'juan@example']);
    }

    public function test_mobile_numbers_are_unique_and_plus_63_is_the_same_number(): void
    {
        $first = $this->signUp($this->registration(['email' => 'first@example.com', 'phone' => '+639171234567']));
        $this->assertSame('09171234567', $first->phone);

        foreach (['09171234567', '+639171234567'] as $phone) {
            $this->post(route('register'), $this->registration(['email' => 'second@example.com', 'phone' => $phone]))
                ->assertSessionHasErrors(['phone' => 'This mobile number is already in use.']);
        }
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);

        $this->post(route('register'), $this->registration(['email' => 'third@example.com', 'phone' => '+63917123456']))->assertSessionHasErrors('phone');
    }

    public function test_buyers_must_be_at_least_18(): void
    {
        $this->post(route('register'), $this->registration(['email' => 'young@example.com', 'date_of_birth' => today()->subYears(18)->addDay()->toDateString()]))
            ->assertSessionHasErrors(['date_of_birth' => 'You must be at least 18 years old to register.']);
        $this->post(route('register'), $this->registration(['email' => 'future@example.com', 'date_of_birth' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['date_of_birth' => 'Enter a valid date of birth. It cannot be a future date.']);

        $this->signUp($this->registration(['email' => 'adult@example.com', 'date_of_birth' => today()->subYears(18)->toDateString()]));
        $this->assertDatabaseHas('users', ['email' => 'adult@example.com']);

        $this->get(route('register'))->assertSee('max="'.today()->subYears(18)->toDateString().'"', false);
    }

    public function test_passwords_need_a_number(): void
    {
        $this->post(route('register'), $this->registration(['password' => 'Mamimili@abcd', 'password_confirmation' => 'Mamimili@abcd']))
            ->assertSessionHasErrors(['password' => 'The password must have at least 8 characters, one uppercase letter, one lowercase letter, one number, and one symbol.']);
    }

    public function test_terms_must_be_accepted(): void
    {
        $data = $this->registration();
        unset($data['terms']);
        $this->post(route('register'), $data)->assertSessionHasErrors(['terms' => 'Please accept the Terms and Privacy Policy to continue.']);
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);

        $this->get(route('register'))->assertSee('name="terms"', false)->assertSee(route('terms'), false);
        $this->get(route('terms'))->assertOk()->assertSee('Terms and Privacy Policy');
    }

    public function test_verification_code_expires_after_5_minutes(): void
    {
        $this->post(route('register'), $this->registration())->assertSessionHasNoErrors();
        $code = $this->signUpCode('buyer@example.com');

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();
        $this->post(route('verification.verify'), ['otp' => $code])
            ->assertSessionHasErrors(['otp' => 'Your code has expired or is unavailable. Please request a new code.']);
        $this->assertDatabaseMissing('users', ['email' => 'buyer@example.com']);
    }

    private function registration(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Juan Miguel', 'last_name' => 'Dela Cruz', 'role' => 'buyer',
            'email' => 'buyer@example.com', 'phone' => '09170000000', 'date_of_birth' => '1998-05-14',
            'password' => 'Mamimili@2026', 'password_confirmation' => 'Mamimili@2026', 'terms' => '1',
        ];
    }
}
