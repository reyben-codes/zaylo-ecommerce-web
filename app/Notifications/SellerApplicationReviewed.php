<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a seller their application was approved, or rejected with the admin's reason. */
class SellerApplicationReviewed extends Notification
{
    public function __construct(public readonly bool $approved, public readonly ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject('Your ZAYLO seller application is approved')
                ->greeting('Welcome to ZAYLO Seller Center!')
                ->line('Your seller application has been approved. You can now sign in, add products and receive orders.')
                ->action('Open Seller Center', route('seller.login'));
        }

        return (new MailMessage)
            ->subject('Your ZAYLO seller application needs changes')
            ->greeting('Your seller application was not approved')
            ->line('Reason from our review team:')
            ->line($this->reason ?: 'No reason was given.')
            ->line('Sign in to correct your details or upload new documents, then resubmit your application.')
            ->action('Update my application', route('seller.login'));
    }
}
