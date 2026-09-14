<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_messages_render_as_dismissible_themed_toasts(): void
    {
        $this->withSession(['status' => 'Product added to your cart.'])
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('data-toast-region', false)
            ->assertSee('toast-notification--success', false)
            ->assertSee('Product added to your cart.')
            ->assertSee('data-toast-close', false)
            ->assertSee('js/notifications.js', false);
    }

    public function test_error_messages_render_as_dismissible_themed_toasts(): void
    {
        $this->withSession(['error' => 'Please try that again.'])
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('toast-notification--error', false)
            ->assertSee('Please try that again.')
            ->assertSee('aria-label="Dismiss notification"', false);
    }
}
