<?php

namespace App\Notifications;

use App\Models\Dispute;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the buyer, seller and courier of an order that a dispute was opened, updated or decided. */
class DisputeUpdated extends Notification
{
    public function __construct(public readonly Dispute $dispute, public readonly string $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->dispute->order?->order_number ?? '#'.$this->dispute->order_id;
        $mail = (new MailMessage)->action('View the case', route('disputes.show', $this->dispute));

        return match ($this->event) {
            'opened' => $mail->subject("A complaint was filed about order {$order}")
                ->line("A complaint about order {$order} was filed: {$this->dispute->category_label}.")
                ->line('The ZAYLO team will review it. You can read the details and reply in the case.'),
            'decided' => $mail->subject("Complaint about order {$order}: {$this->dispute->status_label}")
                ->line("The ZAYLO team has marked the complaint about order {$order} as {$this->dispute->status_label}.")
                ->line('Decision: '.($this->dispute->resolution ?: 'No details were given.')),
            default => $mail->subject("Update on the complaint about order {$order}")
                ->line("There is a new update on the complaint about order {$order}."),
        };
    }
}
