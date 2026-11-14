<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * GCash without the GCash API. The buyer sends each seller's share to that seller's own GCash number and enters the
 * 13-digit reference number (at checkout or later from My Orders). The seller checks it in their GCash app and marks
 * it received, which unlocks Confirm order, or not found, so the buyer can enter it again. Orders still unpaid after
 * EXPIRE_HOURS are cancelled and their stock restored.
 *
 * Payment statuses: awaiting_reference -> pending_verification -> paid, or rejected (back to the buyer).
 */
class GcashPayments
{
    public const EXPIRE_HOURS = 24;

    /** GCash reference numbers are 13 digits; spaces the buyer copies along are ignored. */
    public const REFERENCE_PATTERN = '/^[0-9]{13}$/D';

    public const AWAITING = 'awaiting_reference';

    public const CHECKING = 'pending_verification';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::AWAITING => 'Waiting for your GCash reference',
        self::CHECKING => 'Seller is checking your payment',
        self::PAID => 'Payment received',
        self::REJECTED => 'Seller could not find this payment',
    ];

    public static function ready(): bool
    {
        return Schema::hasColumn('payments', 'gcash_reference') && Schema::hasColumn('users', 'gcash_number');
    }

    /** Checkout's optional reference per seller: gcash_references[shop id] => 13 digits. */
    public static function checkoutRules(): array
    {
        return [
            'gcash_references' => ['nullable', 'array', 'max:50'],
            'gcash_references.*' => ['nullable', 'string', 'max:25', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! preg_match(self::REFERENCE_PATTERN, self::normalize((string) $value))) {
                    $fail('A GCash reference number has 13 digits. Check the receipt in your GCash app.');
                }
            }],
        ];
    }

    public static function normalize(?string $reference): string
    {
        return preg_replace('/\s+/', '', (string) $reference);
    }

    public static function isGcash(Order $order): bool
    {
        return $order->payment_method === 'gcash' && self::ready();
    }

    /** The seller account that owns a product (a user on the deployed schema, the shop's owner on the newer one). */
    public static function sellerUser(?Product $product): ?User
    {
        $seller = $product?->seller;

        return $seller instanceof User ? $seller : $seller?->owner;
    }

    /** Orders keep the shop id: the seller's user id on the deployed schema, sellers.id on the newer one. */
    public static function sellerOfOrder(Order $order): ?User
    {
        if (! $order->seller_id) {
            return null;
        }

        return Product::usesLegacySchema()
            ? User::find((int) $order->seller_id)
            : \App\Models\Seller::with('owner')->find((int) $order->seller_id)?->owner;
    }

    /** @return array{number: string, name: string}|null */
    public static function account(?User $seller): ?array
    {
        return $seller && self::ready() && $seller->gcash_number
            ? ['number' => $seller->gcash_number, 'name' => $seller->gcash_name ?: $seller->name]
            : null;
    }

    /**
     * Checkout: each seller in the cart with their GCash account and items subtotal.
     *
     * @return Collection<int, array{seller_id: int, shop: string, account: ?array, subtotal: float}>
     */
    public function sellersFor(Collection $cartItems): Collection
    {
        return $cartItems->groupBy(fn ($item) => (int) ($item->product->seller_id ?? 0))->map(function (Collection $items, int $sellerId) {
            $user = self::sellerUser($items->first()->product);

            return [
                'seller_id' => $sellerId,
                'shop' => $user?->sellerStoreName() ?? $user?->name ?? 'ZAYLO seller',
                'account' => self::account($user),
                'subtotal' => round((float) $items->sum(fn ($item) => $item->unitPrice() * $item->quantity), 2),
            ];
        })->values();
    }

    /** What the buyer's order page and the app show about the payment. */
    public static function forBuyer(Order $order): ?array
    {
        if (! self::isGcash($order) || ! $order->payment) {
            return null;
        }
        $payment = $order->payment;
        $status = $payment->status;

        return [
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? str($status)->headline()->toString(),
            'reference' => $payment->gcash_reference,
            'rejection_note' => $status === self::REJECTED ? $payment->rejection_note : null,
            'amount' => (float) $order->total,
            'seller_account' => self::account(self::sellerOfOrder($order)),
            'can_submit' => $order->status === 'placed' && in_array($status, [self::AWAITING, self::REJECTED], true),
            'expires_at' => $order->status === 'placed' && $status !== self::PAID
                ? ($order->placed_at ?? $order->created_at)?->copy()->addHours(self::EXPIRE_HOURS)->toIso8601String()
                : null,
        ];
    }

    /** The payment fields checkout writes for a new GCash order. */
    public static function checkoutPayment(?string $reference): array
    {
        if (! self::ready()) {
            return [];
        }

        return $reference
            ? ['status' => self::CHECKING, 'gcash_reference' => $reference, 'reference_submitted_at' => now()]
            : ['status' => self::AWAITING];
    }

    public function submit(Order $order, User $buyer, string $reference): Payment
    {
        abort_unless(self::isGcash($order) && (int) $order->user_id === (int) $buyer->id, 404);
        $reference = self::normalize($reference);
        if (! preg_match(self::REFERENCE_PATTERN, $reference)) {
            throw ValidationException::withMessages(['gcash_reference' => 'Enter the 13-digit reference number from your GCash receipt.']);
        }

        return DB::transaction(function () use ($order, $reference) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            $current = $lockedOrder->status;
            if ($current !== 'placed' || $payment->paid_at !== null || ! in_array($payment->status, [self::AWAITING, self::REJECTED], true)) {
                throw ValidationException::withMessages(['gcash_reference' => $payment->status === self::CHECKING
                    ? 'The seller is already checking your reference.'
                    : 'This order no longer needs a GCash reference.']);
            }
            $payment->forceFill([
                'gcash_reference' => $reference, 'reference_submitted_at' => now(), 'status' => self::CHECKING, 'rejection_note' => null,
            ])->save();

            return $payment;
        });
    }

    /** The seller found the money in their GCash (received), or did not (with a note for the buyer). */
    public function verify(Order $order, User $seller, bool $received, ?string $note = null): Payment
    {
        abort_unless(self::isGcash($order), 404);

        return DB::transaction(function () use ($order, $seller, $received, $note) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->status !== 'placed' || $payment->status !== self::CHECKING || $payment->paid_at !== null) {
                throw ValidationException::withMessages(['gcash' => 'There is no GCash reference waiting for your check on this order.']);
            }
            $payment->forceFill($received
                ? ['status' => self::PAID, 'paid_at' => now(), 'verified_at' => now(), 'verified_by' => $seller->id, 'rejection_note' => null]
                : ['status' => self::REJECTED, 'verified_at' => now(), 'verified_by' => $seller->id, 'rejection_note' => $note])->save();
            $lockedOrder->statusHistory()->create([
                'changed_by' => $seller->id, 'status' => $lockedOrder->status,
                'note' => $received
                    ? 'Seller received the GCash payment (ref. '.$payment->gcash_reference.').'
                    : 'Seller could not find GCash ref. '.$payment->gcash_reference.($note ? ': '.$note : '').'.',
            ]);

            return $payment;
        });
    }

    /** Whether the seller may confirm the order: COD always, GCash once the payment is received. */
    public static function allowsConfirm(Order $order): bool
    {
        return ! self::isGcash($order) || $order->payment?->status === self::PAID;
    }

    /** Cancels GCash orders still unpaid EXPIRE_HOURS after they were placed. Runs when order lists open. */
    public function expireDue(CheckoutService $checkout, ?int $buyerId = null, ?int $shopId = null): int
    {
        if (! self::ready()) {
            return 0;
        }
        $due = Order::query()
            ->where('payment_method', 'gcash')->where('status', 'placed')
            ->where(fn ($q) => $q->where('placed_at', '<', now()->subHours(self::EXPIRE_HOURS))
                ->orWhere(fn ($q) => $q->whereNull('placed_at')->where('created_at', '<', now()->subHours(self::EXPIRE_HOURS))))
            ->whereHas('payment', fn ($q) => $q->where('status', '!=', self::PAID)->whereNull('paid_at'))
            ->when($buyerId, fn ($q) => $q->where('user_id', $buyerId))
            ->when($shopId, fn ($q) => $q->where('seller_id', $shopId))
            ->limit(50)->get();
        $cancelled = 0;
        foreach ($due as $order) {
            $cancelled += (int) $checkout->cancelUnpaid($order, 'No GCash payment was received within '.self::EXPIRE_HOURS.' hours, so the order was cancelled.');
        }

        return $cancelled;
    }
}
