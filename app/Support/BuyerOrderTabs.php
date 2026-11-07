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
        'to_ship' => 'To ship',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
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
            'cancelled' => $orders->where('status', 'cancelled')->where(self::cancelledColumn(), '>=', self::cutoff()),
            'history' => $orders->where('status', 'cancelled')->where(self::cancelledColumn(), '<', self::cutoff()),
            default => $orders->whereIn('status', MobilePayload::STATUS_GROUPS[$tab] ?? []),
        };
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
