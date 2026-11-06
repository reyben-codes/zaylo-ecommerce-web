<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCode extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The code leads the subject so it shows in notification previews, and phones that read
        // codes from mail (iOS/macOS Mail with Safari or the keyboard) can offer it as autofill.
        return (new MailMessage)
            ->subject("{$this->code} is your ZAYLO verification code")
            ->greeting('Welcome to ZAYLO!')
            ->line('Your ZAYLO verification code is:')
            ->line($this->code)
            ->line('Enter it in the verification step to confirm your email address. This code expires in 5 minutes. Do not share it with anyone.')
            ->line('If you did not request this code, you can ignore this email.');
    }
}
