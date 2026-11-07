<?php

namespace App\Notifications;

use App\Models\Voucher;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invites a shop to join a seller-funded voucher: joining applies it to the shop's items, declining leaves it out. */
class VoucherInvitation extends Notification
{
    public function __construct(public readonly Voucher $voucher) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $voucher = $this->voucher;
        $dates = $voucher->expires_at ? 'until '.$voucher->expires_at->timezone('Asia/Manila')->format('M j, Y') : 'until ZAYLO ends it';

        return (new MailMessage)
            ->subject("Join the {$voucher->code} voucher?")
            ->greeting('A new ZAYLO voucher is open to your shop')
            ->line(($voucher->name ? $voucher->name.': ' : '').$voucher->describe().", {$dates}.")
            ->line('If you join, buyers can use it on your products and your shop pays the discount (it comes off your payout for those orders).')
            ->line('If you decline or do not answer, the voucher does not apply to your products.')
            ->action('Join or decline', route('seller.vouchers'));
    }
}
