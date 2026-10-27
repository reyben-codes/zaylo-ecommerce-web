<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The rider delivers a parcel with a photo, but only the buyer finishes the order: confirming they received it marks
 * the payment paid and earns the seller's commission. Orders the buyer leaves unconfirmed complete on their own
 * graceDays() after delivery, unless a complaint about them is still open.
 *
 * The scheduler runs autoConfirmDue() hourly; the order pages run it too, since the host may not run the scheduler.
 */
class OrderReceipts
{
    /** Complaint statuses that keep a delivered order from completing on its own. */
    private const OPEN_COMPLAINTS = ['open', 'in_review'];

    public static function graceDays(): int
    {
        return max(1, (int) config('marketplace.receipt_grace_days', 3));
    }

    public static function awaitingBuyer(Order $order): bool
    {
        return Order::usesLegacySchema()
            ? $order->status === 'delivered'
            : $order->sellerOrders()->where('status', 'delivered')->exists();
    }

    /** When an order waiting for the buyer completes on its own; null once it is not waiting. */
    public static function autoConfirmAt(Order $order): ?Carbon
    {
        if (! self::awaitingBuyer($order)) {
            return null;
        }
        $deliveredAt = Order::usesLegacySchema()
            ? ($order->delivered_at ?? $order->shipment?->delivered_at ?? $order->updated_at)
            : Carbon::parse($order->sellerOrders()->where('status', 'delivered')->max('delivered_at') ?? $order->updated_at);

        return $deliveredAt?->copy()->addDays(self::graceDays());
    }

    /** The buyer received the order. */
    public function confirm(Order $order, User $buyer): void
    {
        $this->complete($order, $buyer, 'Buyer confirmed they received the order.');
    }

    /** Completes delivered orders whose grace period ended; returns how many. */
    public function autoConfirmDue(): int
    {
        $cutoff = now()->subDays(self::graceDays());
        $due = Order::usesLegacySchema()
            ? Order::where('status', 'delivered')->where(fn (Builder $query) => Schema::hasColumn('orders', 'delivered_at')
                ? $query->where('delivered_at', '<=', $cutoff)->orWhere(fn ($undated) => $undated->whereNull('delivered_at')->where('updated_at', '<=', $cutoff))
                : $query->where('updated_at', '<=', $cutoff))
            : Order::whereHas('sellerOrders', fn ($sellerOrder) => $sellerOrder->where('status', 'delivered')
                ->where(fn ($dated) => $dated->where('delivered_at', '<=', $cutoff)->orWhere(fn ($undated) => $undated->whereNull('delivered_at')->where('updated_at', '<=', $cutoff))));
        if (Schema::hasTable('disputes')) {
            $due->whereDoesntHave('disputes', fn ($dispute) => $dispute->whereIn('status', self::OPEN_COMPLAINTS));
        }

        $completed = 0;
        foreach ($due->limit(200)->get() as $order) {
            try {
                $this->complete($order, null, 'Delivery confirmed automatically '.self::graceDays().' days after the rider delivered it.');
                $completed++;
            } catch (ValidationException) {
                // The buyer confirmed it in the meantime.
            }
        }

        return $completed;
    }

    private function complete(Order $order, ?User $actor, string $note): void
    {
        DB::transaction(function () use ($order, $actor, $note) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! self::awaitingBuyer($locked)) {
                throw ValidationException::withMessages(['order' => 'This order is not waiting for you to confirm delivery.']);
            }

            if (Order::usesLegacySchema()) {
                $locked->update(['status' => 'completed', 'completed_at' => now()]);
                $locked->payment?->update(['status' => 'paid', 'paid_at' => now()]);
                if ($locked->seller_id && Schema::hasTable('commissions')) {
                    DB::table('commissions')->updateOrInsert(['order_id' => $locked->id], [
                        'seller_id' => $locked->seller_id, 'rate' => 10,
                        'amount' => round((float) $locked->subtotal * 0.10, 2),
                        'status' => 'earned', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            } else {
                $locked->sellerOrders()->where('status', 'delivered')->update(['status' => 'completed', 'updated_at' => now()]);
            }

            if (Schema::hasTable('order_status_histories')) {
                $locked->statusHistory()->create(['changed_by' => $actor?->id, 'status' => 'completed', 'note' => $note]);
            }
        });
    }
}
