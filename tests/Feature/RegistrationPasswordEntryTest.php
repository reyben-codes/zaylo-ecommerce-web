<?php

namespace Tests\Feature;

use Tests\TestCase;

class RegistrationPasswordEntryTest extends TestCase
{
    public function test_manual_password_controls_apply_to_all_registration_roles(): void
    {
        foreach (['buyer', 'seller', 'courier'] as $role) {
            $response = $this->get(route('register', ['role' => $role]))->assertOk()
                ->assertSee('js/registration-password-entry.js')->assertSee('Please type both passwords manually.');
            $this->assertSame(2, substr_count($response->getContent(), 'data-manual-password'));
        }
        $this->get(route('login'))->assertOk()->assertDontSee('data-manual-password');
    }
}
