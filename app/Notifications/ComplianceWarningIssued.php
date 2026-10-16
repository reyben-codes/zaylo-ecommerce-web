<?php

namespace App\Notifications;

use App\Models\ComplianceWarning;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a seller about a compliance warning and what was done (product hidden or account suspended). */
class ComplianceWarningIssued extends Notification
{
    public function __construct(public readonly ComplianceWarning $warning) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->warning->product?->name;
        $mail = (new MailMessage)
            ->subject('ZAYLO policy warning: '.$this->warning->reason_label)
            ->greeting('A policy warning was issued for your shop')
            ->line('Reason: '.$this->warning->reason_label.($product ? " (product: {$product})" : '').'.')
            ->line($this->warning->details);

        return match ($this->warning->action) {
            'product_hidden' => $mail->line('The product has been hidden from buyers. Edit it so it follows the policy, then contact us to review it again.')
                ->action('Open Seller Center', route('seller.login')),
            'suspended' => $mail->line('Your seller account has been suspended. Contact the ZAYLO team to appeal.'),
            default => $mail->line('Please correct this. Repeated violations can lead to your products being hidden or your account suspended.')
                ->action('Open Seller Center', route('seller.login')),
        };
    }
}
