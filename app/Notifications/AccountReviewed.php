<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a buyer, logistics partner or rider that their registration was approved or rejected,
 * or that their account was suspended, deactivated or restored.
 */
class AccountReviewed extends Notification
{
    private const PORTALS = [
        'buyer' => ['ZAYLO', 'login'],
        'logistics' => ['the ZAYLO Logistics Center', 'logistics.login'],
        'rider' => ['the ZAYLO rider portal', 'login'],
        'seller' => ['ZAYLO Seller Center', 'seller.login'],
    ];

    public function __construct(public readonly string $status, public readonly ?string $reason = null, public readonly ?string $reviewer = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $role = $notifiable->role === 'courier' ? 'rider' : $notifiable->role;
        [$portal, $loginRoute] = self::PORTALS[$role] ?? self::PORTALS['buyer'];
        $reviewer = $this->reviewer ?? 'the ZAYLO team';
        $mail = new MailMessage;

        return match ($this->status) {
            'active' => $mail->subject('Your ZAYLO account is approved')
                ->greeting('Welcome to ZAYLO!')
                ->line("Your account has been approved by {$reviewer}. You can now sign in to {$portal}.")
                ->action('Sign in', route($loginRoute)),
            'rejected' => $this->withReason($mail->subject('Your ZAYLO registration was not approved')
                ->greeting('Your registration was not approved'))
                ->line('If you think this is a mistake, reply to this email or register again with the correct details.'),
            'suspended' => $this->withReason($mail->subject('Your ZAYLO account is suspended')
                ->greeting('Your account is suspended'))
                ->line('While suspended you cannot sign in. Contact the ZAYLO team if you want to appeal.'),
            'deactivated' => $this->withReason($mail->subject('Your ZAYLO account is deactivated')
                ->greeting('Your account is deactivated'))
                ->line('You can no longer sign in with this account. Contact the ZAYLO team if you want it reopened.'),
            default => $mail->subject('Your ZAYLO account is under review')
                ->line('Your account is waiting for review again. We will email you when a decision is made.'),
        };
    }

    private function withReason(MailMessage $mail): MailMessage
    {
        return $this->reason ? $mail->line('Reason from our review team:')->line($this->reason) : $mail;
    }
}
