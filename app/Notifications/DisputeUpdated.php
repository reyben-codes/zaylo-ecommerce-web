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
            'return_received' => $mail->subject("Your return for order {$order} reached the seller")
                ->line("The seller received the item you returned from order {$order}.")
                ->line('They will now process your refund or replacement. You can follow it in the case.'),
            'return_step' => $mail->subject("Return for order {$order}: {$this->dispute->returnStepLabel()}")
                ->line("The returned item from order {$order} is now: {$this->dispute->returnStepLabel()}.")
                ->line('You can follow every step of the return in the case.'),
            'refunded' => $mail->subject("Refund issued for order {$order}")
                ->line('The seller issued a refund of ₱'.number_format((float) $this->dispute->refund_amount, 2)." for order {$order}.")
                ->line('The case is now closed. Contact ZAYLO support if the money does not reach you.'),
            default => $mail->subject("Update on the complaint about order {$order}")
                ->line("There is a new update on the complaint about order {$order}."),
        };
    }
}
