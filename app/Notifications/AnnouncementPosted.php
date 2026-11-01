<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** An admin announcement for the audience that was chosen to receive it by email. */
class AnnouncementPosted extends Notification
{
    public function __construct(public readonly Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->announcement->title)
            ->line($this->announcement->body)
            ->action('Open ZAYLO', route('home'));
    }
}
