<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * The buyer's order tabs (website and app) and the cancellation reasons they choose from.
 *
 * Cancelled orders stay under Cancelled for HISTORY_AFTER_DAYS days (time to Buy again), then move to Purchase
 * history, which keeps them for good.
 */
final class BuyerOrderTabs
{
    public const HISTORY_AFTER_DAYS = 2;

    public const LABELS = [
        'all' => 'All orders',
        'to_pay' => 'To pay',
        'to_ship' => 'To ship',
        'shipped' => 'In transit / Out for delivery',
        'to_receive' => 'To receive',
        'delivered' => 'Completed',
        'cancelled' => 'Cancelled',
        'history' => 'Purchase history',
    ];

    /** Why a buyer cancels, like the big marketplaces ask. "other" needs a note. */
    public const CANCEL_REASONS = [
        'change_address' => 'I need to change the delivery address',
        'change_items' => 'I want to change items or quantities',
        'found_cheaper' => 'I found a cheaper price elsewhere',
        'payment_issue' => 'I want to change the payment method',
        'ordered_by_mistake' => 'I ordered by mistake',
        'too_long' => 'It is taking too long to ship',
        'other' => 'Other reason',
    ];

    public static function tabs(): array
    {
        return array_keys(self::LABELS);
    }

    public static function apply(Builder $orders, string $tab): Builder
    {
        return match ($tab) {
            'all' => $orders,
            'to_pay' => $orders->where('status', 'placed')->where('payment_method', 'gcash')
                ->whereHas('payment', fn ($payment) => $payment->where('status', '!=', 'paid')->whereNull('paid_at')),
            'to_ship' => self::status($orders, MobilePayload::STATUS_GROUPS['to_ship'])
                ->where(fn ($q) => $q->where('payment_method', '!=', 'gcash')->orWhereHas('payment', fn ($payment) => $payment->where('status', 'paid'))),
            'to_receive' => self::status($orders, ['delivered']),
            'delivered' => self::status($orders, ['completed']),
            'cancelled' => $orders->where('status', 'cancelled')->where(self::cancelledColumn(), '>=', self::cutoff()),
            'history' => $orders->where('status', 'cancelled')->where(self::cancelledColumn(), '<', self::cutoff()),
            default => self::status($orders, MobilePayload::STATUS_GROUPS[$tab] ?? []),
        };
    }

    private static function status(Builder $orders, array $statuses): Builder
    {
        if (Order::usesLegacySchema()) return $orders->whereIn('status', $statuses);
        $map = ['placed' => 'pending', 'confirmed' => 'accepted', 'processing' => 'packed', 'ready_for_pickup' => 'ready_to_ship',
            'assigned' => 'shipped', 'picked_up' => 'shipped', 'in_transit' => 'shipped', 'out_for_delivery' => 'shipped',
            'delivered' => 'delivered', 'completed' => 'completed'];
        // Rider assignment means shipped on the normalized schema, so it belongs under In Transit.
        if (in_array('placed', $statuses, true)) $statuses = array_diff($statuses, ['assigned']);
        $stored = array_values(array_unique(array_map(fn ($s) => $map[$s] ?? $s, $statuses)));
        return $orders->whereHas('sellerOrders', fn ($q) => $q->whereIn('status', $stored));
    }

    /** @return array<string, int> tab => number of the buyer's orders in it */
    public static function counts(Builder $orders): array
    {
        return collect(self::tabs())->mapWithKeys(fn (string $tab) => [$tab => self::apply(clone $orders, $tab)->count()])->all();
    }

    /** When the order was cancelled: the recorded time, or its last update for orders cancelled before it was kept. */
    public static function cancelledAt(Order $order): ?\Illuminate\Support\Carbon
    {
        $at = Schema::hasColumn('orders', 'cancelled_at') ? $order->cancelled_at : null;

        return $at ? \Illuminate\Support\Carbon::parse($at) : $order->updated_at;
    }

    public static function reasonLabel(?string $reason): ?string
    {
        return $reason ? (self::CANCEL_REASONS[$reason] ?? null) : null;
    }

    private static function cancelledColumn(): \Illuminate\Contracts\Database\Query\Expression|string
    {
        return Schema::hasColumn('orders', 'cancelled_at')
            ? \Illuminate\Support\Facades\DB::raw('COALESCE(cancelled_at, updated_at)')
            : 'updated_at';
    }

    private static function cutoff(): \Illuminate\Support\Carbon
    {
        return now()->subDays(self::HISTORY_AFTER_DAYS);
    }
}
