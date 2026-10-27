<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

/** Render's free plan blocks SMTP ports, so the live site sends verification codes through Brevo's HTTPS API. */
class MailTransportTest extends TestCase
{
    public function test_the_brevo_mailer_sends_over_https_with_the_configured_key(): void
    {
        config(['mail.mailers.brevo.key' => 'test-key']);

        $transport = Mail::mailer('brevo')->getSymfonyTransport();

        $this->assertInstanceOf(BrevoApiTransport::class, $transport);
        $this->assertSame('brevo+api://api.brevo.com', (string) $transport);
    }
}
