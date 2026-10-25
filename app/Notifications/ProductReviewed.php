<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a seller whether an admin approved their listing, or why it was rejected. */
class ProductReviewed extends Notification
{
    public function __construct(public readonly Product $product) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->product->name;

        if ($this->product->is_rejected) {
            return (new MailMessage)
                ->subject("Your product \"{$name}\" was not approved")
                ->greeting('Your listing needs changes')
                ->line("A ZAYLO admin reviewed \"{$name}\" and did not approve it, so buyers cannot see it.")
                ->line('Reason: '.$this->product->review_note)
                ->line('Edit the product to fix this and save it. It goes back to the admin for another review.')
                ->action('Edit product', route('seller.products.edit', $this->product));
        }

        return (new MailMessage)
            ->subject("Your product \"{$name}\" is approved")
            ->greeting('Your listing is live')
            ->line($this->product->is_active
                ? "\"{$name}\" was approved and buyers can now find it in your shop."
                : "\"{$name}\" was approved. It is set to hidden, so switch it to active when you are ready to sell it.")
            ->action('View product', route('products.show', $this->product));
    }
}
