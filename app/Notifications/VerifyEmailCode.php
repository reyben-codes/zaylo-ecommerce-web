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
        return (new MailMessage)
            ->subject('Your ZAYLO verification code')
            ->greeting('Welcome to ZAYLO!')
            ->line('Enter this six-digit code in the verification step to confirm your email address:')
            ->line($this->code)
            ->line('This code expires in 10 minutes. Do not share it with anyone.')
            ->line('If you did not request this code, you can ignore this email.');
    }
}
