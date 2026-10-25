<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\RiderDeliveries;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent as the rider moves a parcel: to the buyer as it sets off, on delivery and when an attempt fails (with the
 * rider's reason), and to the seller on delivery or a failed attempt.
 */
class ParcelDeliveryUpdate extends Notification
{
    public function __construct(
        public readonly Shipment $shipment,
        public readonly Order $order,
        public readonly string $step,
        public readonly bool $forSeller,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->order_number ?? $this->order->reference;
        $parcel = RiderDeliveries::labelCode($this->shipment);

        if ($this->forSeller) {
            return $this->step === 'failed'
                ? (new MailMessage)
                    ->subject("Order {$order} could not be delivered")
                    ->line("The rider could not deliver parcel {$parcel} for order {$order}.")
                    ->line('Reason: '.($this->reason ?? 'Not given').'.')
                    ->line('Your logistics partner will arrange another attempt or return the parcel to you.')
                    ->action('View orders', route('seller.orders'))
                : (new MailMessage)
                    ->subject("Order {$order} was delivered")
                    ->line("The rider scanned parcel {$parcel} and handed order {$order} to the buyer.")
                    ->action('View orders', route('seller.orders'));
        }

        return match ($this->step) {
            'delivered' => (new MailMessage)
                ->subject("Your order {$order} was delivered")
                ->line("Your parcel {$parcel} was handed over by the rider.")
                ->line('If you did not receive it, you can file a complaint from your orders.')
                ->action('View orders', route('buyer.orders')),
            'failed' => (new MailMessage)
                ->subject("We could not deliver your order {$order}")
                ->line("The rider could not deliver your parcel {$parcel}.")
                ->line('Reason: '.($this->reason ?? 'Not given').'.')
                ->line('If this is a mistake, message the rider or ZAYLO support from your orders.')
                ->action('View orders', route('buyer.orders')),
            default => (new MailMessage)
                ->subject("Your order {$order} is being delivered")
                ->line("Your parcel {$parcel} is out for delivery. The rider will hand it over soon.")
                ->line('Please keep your phone nearby in case the rider needs to reach you.')
                ->action('View orders', route('buyer.orders')),
        };
    }
}
