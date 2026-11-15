<?php

namespace App\Services;

use App\Models\EscrowPayment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Local-only authorization adapter. It never calls a bank, PayMongo, or GCash. */
class DemoPayments
{
    public static function enabled(): bool
    {
        return (bool) config('escrow.demo_enabled') && ! (bool) config('escrow.live');
    }

    public static function isDemo(EscrowPayment $payment): bool
    {
        return ! $payment->livemode && str_starts_with((string) $payment->checkout_session, 'demo_');
    }

    /** The authenticated owner obtains this short-lived capability through the normal pay endpoint. */
    public function checkoutUrl(EscrowPayment $payment): string
    {
        abort_unless(self::enabled() && self::isDemo($payment), 404);
        return URL::temporarySignedRoute('payment.checkout', now()->addMinutes(10), ['escrow' => $payment->id]);
    }

    public function authorize(EscrowPayment $payment, string $decision): void
    {
        abort_unless(self::enabled() && self::isDemo($payment), 404);
        if (! in_array($decision, ['approve', 'decline'], true)) throw ValidationException::withMessages(['payment' => 'Choose a payment action.']);
        DB::transaction(function () use ($payment, $decision) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = EscrowPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless(self::isDemo($locked) && $order->payment_method === 'gcash', 409);
            if (in_array($locked->status, ['held', 'released'], true)) return;
            if ($locked->status !== 'pending' || $order->status !== 'placed') {
                throw ValidationException::withMessages(['payment' => 'This order is no longer awaiting payment.']);
            }
            if ($decision === 'decline') {
                app(ActivityLogger::class)->record('payment.demo_declined', 'settlement', 'Local payment authorization declined.', $order->buyer, $locked,
                    ['simulation' => true, 'amount_minor' => $locked->amount_minor]);
                return;
            }
            // Amounts are read from the reserved server-side order, never from form fields.
            abort_unless((int) round($order->total * 100) === (int) $locked->amount_minor, 409, 'The order total changed. Please contact support.');
            do {
                $reference = 'REF'.Str::upper(Str::random(10));
            } while (EscrowPayment::where('gateway_payment', $reference)->exists());
            $locked->update(['status' => 'held', 'gateway_payment' => $reference, 'gateway_fee_minor' => 0, 'captured_at' => now()]);
            $order->payment?->update(['status' => 'paid', 'paid_at' => now()]);
            $order->statusHistory()->create(['status' => 'placed', 'note' => 'Payment authorized; funds held pending delivery confirmation.']);
            $updates = app(AccountUpdates::class);
            $updates->send($order->buyer?->id, 'escrow-held-'.$locked->id, 'orders', 'Payment successful',
                'Order '.$order->order_number.': payment authorized. Your order is now To Ship.', 'order', $order->id);
            $updates->send(GcashPayments::sellerOfOrder($order)?->id, 'escrow-held-'.$locked->id, 'orders', 'Paid order ready to prepare',
                'Order '.$order->order_number.' is paid. Prepare the parcel; funds release after delivery confirmation.', 'order', $order->id);
            app(ActivityLogger::class)->record('payment.demo_authorized', 'settlement', 'Local payment authorization completed.', $order->buyer, $locked,
                ['simulation' => true, 'amount_minor' => $locked->amount_minor, 'reference' => $reference]);
        });
    }
}
