<?php

namespace App\Services;

use App\Models\EscrowPayment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EscrowPayments
{
    public static function configured(): bool
    {
        if (config('escrow.demo_enabled')) {
            return DemoPayments::enabled() && filled(config('escrow.admin_user_id')) && Schema::hasTable('escrow_payments');
        }
        $key = (string) config('escrow.secret_key');
        return str_starts_with($key, config('escrow.live') ? 'sk_live_' : 'sk_test_')
            && filled(config('escrow.webhook_secret')) && filled(config('escrow.admin_user_id')) && Schema::hasTable('escrow_payments');
    }

    public static function forOrder(Order $order): ?EscrowPayment
    {
        return Schema::hasTable('escrow_payments') ? EscrowPayment::where('order_id', $order->id)->first() : null;
    }

    public static function reserve(Order $order): EscrowPayment
    {
        return EscrowPayment::firstOrCreate(['order_id' => $order->id], [
            'amount_minor' => (int) round($order->total * 100), 'livemode' => (bool) config('escrow.live'),
            'checkout_session' => DemoPayments::enabled() ? 'demo_'.\Illuminate\Support\Str::uuid() : null,
        ]);
    }

    /** Only the server creates sessions. OTP/MPIN stays on the gateway's own screens. */
    public function checkout(Order $order): string
    {
        abort_unless(self::configured(), 503, 'GCash gateway is not configured yet. Please use Cash on Delivery.');
        return DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->payment_method === 'gcash' && in_array($locked->status, ['placed', 'awaiting_payment'], true), 422, 'This order is not awaiting payment.');
            $escrow = EscrowPayment::where('order_id', $locked->id)->lockForUpdate()->firstOrFail();
            if (DemoPayments::isDemo($escrow)) {
                abort_unless(DemoPayments::enabled(), 409, 'This checkout is unavailable.');
                return app(DemoPayments::class)->checkoutUrl($escrow);
            }
            abort_if(config('escrow.demo_enabled'), 409, 'This order uses a different payment provider. Create a new order for the current checkout.');
            abort_unless($escrow->status === 'pending', 422, 'This payment is no longer pending.');
            abort_unless($escrow->livemode === (bool) config('escrow.live'), 409, 'Payment mode has changed. Contact support.');
            if ($escrow->checkout_url) return $escrow->checkout_url;
            abort_if($escrow->created_at->lt(now()->subHours(23)), 409, 'This checkout needs support reconciliation before another payment attempt.');
            try {
                $response = Http::withBasicAuth(config('escrow.secret_key'), '')->acceptJson()->timeout(15)
                ->withHeaders(['Idempotency-Key' => 'zaylo-order-'.$locked->id])
                ->post('https://api.paymongo.com/v2/checkout_sessions', ['data' => ['attributes' => [
                    'line_items' => [['name' => 'ZAYLO order '.$locked->order_number, 'amount' => $escrow->amount_minor, 'currency' => 'PHP', 'quantity' => 1]],
                    'payment_method_types' => ['gcash'], 'reference_number' => $locked->order_number,
                    'success_url' => route('escrow.return', $locked), 'cancel_url' => route('escrow.return', $locked),
                    'metadata' => ['order_id' => (string) $locked->id], 'send_email_receipt' => true,
                ]]]);
            } catch (\Illuminate\Http\Client\ConnectionException) {
                throw ValidationException::withMessages(['payment' => 'Could not connect to the payment gateway. Your order is saved under To Pay; retry the same checkout.']);
            }
            if (! $response->successful()) {
                throw ValidationException::withMessages(['payment' => 'The payment gateway is unavailable. Your order is saved under To Pay; please retry.']);
            }
            $session = $response->json('data');
            $url = data_get($session, 'attributes.checkout_url');
            abort_unless(is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https'
                && parse_url($url, PHP_URL_HOST) === 'checkout.paymongo.com'
                && is_string(data_get($session, 'id'))
                && data_get($session, 'attributes.livemode') === $escrow->livemode, 502, 'Invalid gateway checkout response.');
            $escrow->update(['checkout_session' => $session['id'], 'checkout_url' => $url]);
            return $url;
        });
    }

    public static function validSignature(string $body, string $header, string $secret, bool $live, ?int $now = null): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) $parts[$pair[0]] = $pair[1];
        }
        $timestamp = $parts['t'] ?? '';
        $signature = $parts[$live ? 'li' : 'te'] ?? '';
        return $secret !== '' && ctype_digit($timestamp) && abs(($now ?? time()) - (int) $timestamp) <= 300
            && $signature !== '' && hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $signature);
    }

    /** A signed gateway event is the only path from pending to held; redirects never settle payments. */
    public function capture(array $event): void
    {
        // PayMongo's current event envelope and its older attributes envelope are both supported.
        $attributes = data_get($event, 'data.attributes', data_get($event, 'data', []));
        if (data_get($attributes, 'type') !== 'checkout_session.payment.paid') return;
        $session = data_get($attributes, 'data');
        $sessionId = data_get($session, 'id');
        if (! is_string($sessionId)) return;
        $candidate = EscrowPayment::where('checkout_session', $sessionId)->first();
        if (! $candidate || DemoPayments::isDemo($candidate)) return;
        DB::transaction(function () use ($candidate, $session, $attributes) {
            // Same order-first lock ordering as cancellation, delivery confirmation and release.
            $order = Order::whereKey($candidate->order_id)->lockForUpdate()->firstOrFail();
            $escrow = EscrowPayment::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            $mode = data_get($attributes, 'livemode', data_get($session, 'attributes.livemode'));
            abort_unless($mode === $escrow->livemode && $mode === (bool) config('escrow.live'), 422, 'Payment mode mismatch.');
            $payments = collect(data_get($session, 'attributes.payments', []))->filter(fn ($p) => data_get($p, 'attributes.status') === 'paid');
            abort_unless($payments->count() === 1, 422, 'Expected one paid payment.');
            $payment = $payments->first();
            abort_unless(data_get($payment, 'attributes.amount') === (int) $escrow->amount_minor
                && data_get($payment, 'attributes.currency') === 'PHP'
                && data_get($session, 'attributes.reference_number') === $order->order_number
                && is_string(data_get($payment, 'id')), 422, 'Payment details do not match this order.');
            if (in_array($escrow->status, ['held', 'released'], true)) {
                abort_unless($escrow->gateway_payment === $payment['id'], 409);
                return;
            }
            // Late paid events must remain recorded for reconciliation; cancelled stock must not be fulfilled.
            $cancelled = $order->status === 'cancelled';
            $escrow->update(['status' => $cancelled ? 'review_required' : 'held', 'gateway_payment' => $payment['id'],
                'gateway_fee_minor' => max(0, (int) data_get($payment, 'attributes.fee', 0)), 'captured_at' => now()]);
            $order->payment?->update(['status' => 'paid', 'paid_at' => now()]);
            if ($order->status === 'awaiting_payment') $order->update(['status' => 'placed', 'placed_at' => now()]);
            if (! $cancelled) {
                // "placed" remains the seller's confirmation step; payment status moves the buyer from To Pay to To Ship.
                $order->statusHistory()->create(['status' => 'placed', 'note' => 'GCash gateway payment verified; funds held pending delivery confirmation.']);
            }
            $updates = app(AccountUpdates::class);
            $updates->send($order->buyer?->id, 'escrow-held-'.$escrow->id, 'orders', 'GCash payment successful',
                'Order '.$order->order_number.': payment verified. '.($cancelled ? 'The order was cancelled; support will reconcile your payment.' : 'Your order is now To Ship.'), 'order', $order->id);
            $seller = GcashPayments::sellerOfOrder($order);
            $updates->send($seller?->id, 'escrow-held-'.$escrow->id, 'orders', 'Paid order ready to prepare', 'Order '.$order->order_number.' is paid through the gateway. Funds release after delivery confirmation.', 'order', $order->id);
            if ($cancelled) $updates->admins('escrow-review-'.$escrow->id, 'Cancelled order payment needs review', 'Gateway payment received for cancelled order '.$order->order_number.'. Refund reconciliation required.');
        });
    }

    /** Atomic internal wallet credit, never a claim that a bank transfer has happened. */
    public function release(Order $order): void
    {
        $candidate = self::forOrder($order);
        if (! $candidate) return;
        DB::transaction(function () use ($order, $candidate) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $escrow = EscrowPayment::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            if ($escrow->status === 'released') return;
            if (DemoPayments::isDemo($escrow)) abort_unless(DemoPayments::enabled(), 409, 'This payment cannot be released in the current environment.');
            if ($locked->status !== 'completed' || $escrow->status !== 'held') {
                throw ValidationException::withMessages(['escrow' => 'Funds release only after a verified payment and confirmed delivery.']);
            }
            if (Schema::hasTable('disputes') && $locked->disputes()->whereIn('status', ['open', 'in_review'])->exists()) {
                throw ValidationException::withMessages(['escrow' => 'An open return or dispute keeps these funds on hold.']);
            }
            if (Schema::hasColumn('disputes', 'refunded_at') && $locked->disputes()->whereNotNull('refunded_at')->exists()) {
                throw ValidationException::withMessages(['escrow' => 'Refunded orders cannot release a second payout to the seller. Reconcile this payment with Admin.']);
            }
            $seller = GcashPayments::sellerOfOrder($locked);
            $shipment = $locked->deliveryShipment();
            $partner = $shipment?->provider?->owner;
            $admin = config('escrow.admin_user_id') ? User::find(config('escrow.admin_user_id')) : null;
            if (! $seller || ! $partner || ! $admin?->hasRole('admin')) {
                throw ValidationException::withMessages(['escrow' => 'Seller, logistics and escrow administrator accounts must be configured before releasing funds.']);
            }
            $split = app(Settlement::class)->forOrder($locked);
            $sellerMinor = (int) round($split['seller_earnings'] * 100);
            $partnerMinor = (int) round(($split['logistics_gross_earnings'] ?? $split['logistics_earnings']) * 100);
            $adminMinor = (int) $escrow->amount_minor - $sellerMinor - $partnerMinor - (int) $escrow->gateway_fee_minor;
            foreach ([[$seller, 'seller', $sellerMinor], [$partner, 'logistics', $partnerMinor], [$admin, 'platform', $adminMinor]] as [$user, $kind, $amount]) {
                DB::table('settlement_wallet_entries')->insert(['escrow_payment_id' => $escrow->id, 'user_id' => $user->id,
                    'kind' => $kind, 'amount_minor' => $amount, 'created_at' => now(), 'updated_at' => now()]);
                app(AccountUpdates::class)->send($user->id, 'escrow-released-'.$escrow->id, $kind === 'seller' ? 'wallet' : 'payouts',
                    'GCash escrow settled', 'Order '.$locked->order_number.': PHP '.number_format($amount / 100, 2).' allocated to your '.($kind === 'seller' ? 'withdrawable wallet' : 'settlement balance').'.');
            }
            $escrow->update(['status' => 'released', 'released_at' => now()]);
            if ($shipment) {
                $riderUser = $shipment->rider?->user ?? ($shipment->courier_id ? User::find($shipment->courier_id) : null);
                if ($riderUser) {
                    app(RiderWallet::class)->credit($shipment->fresh(), $riderUser);
                }
            }
            app(AccountUpdates::class)->admins('escrow-released-'.$escrow->id, 'GCash escrow settled',
                'Order '.$locked->order_number.': verified payment released to seller and delivery wallets after fees.');
            app(AccountUpdates::class)->send($locked->buyer?->id, 'escrow-released-'.$escrow->id, 'orders', 'Order payment settled',
                'Order '.$locked->order_number.': delivery confirmed and payment released to the seller and delivery partners.', 'order', $locked->id);
            app(ActivityLogger::class)->record('escrow.released', 'settlement', 'Verified GCash payment allocated to wallets.', null, $escrow,
                ['seller_minor' => $sellerMinor, 'logistics_minor' => $partnerMinor, 'platform_minor' => $adminMinor, 'gateway_fee_minor' => $escrow->gateway_fee_minor]);
        });
    }
}
