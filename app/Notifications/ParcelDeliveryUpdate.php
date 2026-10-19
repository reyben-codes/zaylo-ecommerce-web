<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\RiderDeliveries;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent when a rider scans a parcel: to the buyer as it sets off and on delivery, and to the seller on delivery. */
class ParcelDeliveryUpdate extends Notification
{
    public function __construct(
        public readonly Shipment $shipment,
        public readonly Order $order,
        public readonly string $step,
        public readonly bool $forSeller,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->order_number;
        $parcel = RiderDeliveries::labelCode($this->shipment);

        if ($this->forSeller) {
            return (new MailMessage)
                ->subject("Order {$order} was delivered")
                ->line("The rider scanned parcel {$parcel} and handed order {$order} to the buyer.")
                ->action('View orders', route('seller.orders'));
        }

        return $this->step === 'delivered'
            ? (new MailMessage)
                ->subject("Your order {$order} was delivered")
                ->line("Your parcel {$parcel} was handed over by the rider.")
                ->line('If you did not receive it, you can file a complaint from your orders.')
                ->action('View orders', route('buyer.orders'))
            : (new MailMessage)
                ->subject("Your order {$order} is being delivered")
                ->line("Your parcel {$parcel} is out for delivery. The rider will hand it over soon.")
                ->line('Please keep your phone nearby in case the rider needs to reach you.')
                ->action('View orders', route('buyer.orders'));
    }
}
