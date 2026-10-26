<?php

namespace App\Services;

use App\Http\Controllers\SellerController;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders with their sales and platform commission, for the admin's commission page and reports.
 * One row per seller's share of an order, the same on the deployed and newer database layouts.
 */
class MarketplaceLedger
{
    public const TIMEZONE = 'Asia/Manila';

    /** Order statuses that count as a finished sale. */
    private const COMPLETED = ['completed', 'delivered'];

    public static function rate(): float
    {
        return SellerController::COMMISSION_RATE;
    }

    /**
     * @return Collection<int, array{order_id:int, number:string, placed:?Carbon, buyer:string, seller_id:?int, seller:string,
     *     status:string, subtotal:float, shipping:float, total:float, commission:float, commission_status:string, commission_id:?int}>
     */
    public function rows(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return Order::usesLegacySchema() ? $this->legacyRows($from, $to) : $this->sellerOrderRows($from, $to);
    }

    /** Totals over rows; cancelled orders are counted but never in sales. */
    public function summary(Collection $rows): array
    {
        $live = $rows->where('status', '!=', 'cancelled');
        $completed = $rows->filter(fn ($row) => in_array($row['status'], self::COMPLETED, true));

        $gross = round($live->sum('subtotal'), 2);
        // ZAYLO's share of every non-cancelled order (earned once completed); sellers keep the rest.
        $commission = round($live->sum('commission'), 2);

        return [
            'orders' => $rows->pluck('order_id')->unique()->count(),
            'gross' => $gross,
            'commission' => $commission,
            'net' => round($gross - $commission, 2),
            'completedSales' => round($completed->sum('subtotal'), 2),
            'completedOrders' => $completed->pluck('order_id')->unique()->count(),
            'cancelled' => $rows->where('status', 'cancelled')->pluck('order_id')->unique()->count(),
            'average' => $live->count() ? round($live->sum('total') / $live->pluck('order_id')->unique()->count(), 2) : 0,
            'shipping' => round($live->sum('shipping'), 2),
            'commissionEarned' => round($rows->whereIn('commission_status', ['earned', 'unrecorded', 'settled'])->sum('commission'), 2),
            'commissionUnrecorded' => $rows->where('commission_status', 'unrecorded')->count(),
            'commissionSettled' => round($rows->where('commission_status', 'settled')->sum('commission'), 2),
            'commissionPending' => round($rows->where('commission_status', 'pending')->sum('commission'), 2),
        ];
    }

    /** Per seller: orders, sales (gross), commission, net and settlement state. */
    public function bySeller(Collection $rows): Collection
    {
        return $rows->groupBy('seller_id')->map(function (Collection $group) {
            $live = $group->where('status', '!=', 'cancelled');

            return [
                'seller_id' => $group->first()['seller_id'],
                'seller' => $group->first()['seller'],
                'orders' => $group->pluck('order_id')->unique()->count(),
                'sales' => round($live->sum('subtotal'), 2),
                'commission' => round($live->sum('commission'), 2),
                'net' => round($live->sum('subtotal') - $live->sum('commission'), 2),
                'earned' => round($group->whereIn('commission_status', ['earned', 'unrecorded'])->sum('commission'), 2),
                'settled' => round($group->where('commission_status', 'settled')->sum('commission'), 2),
                'pending' => round($group->where('commission_status', 'pending')->sum('commission'), 2),
            ];
        })->sortByDesc('sales')->values();
    }

    /** Orders and sales per day (or per month for long ranges), in Philippine time. */
    public function series(Collection $rows, Carbon $from, Carbon $to): Collection
    {
        $monthly = $from->diffInDays($to) > 62;
        $format = $monthly ? 'Y-m' : 'Y-m-d';
        $buckets = collect();
        for ($day = $from->copy()->timezone(self::TIMEZONE)->startOfDay(); $day->lte($to->copy()->timezone(self::TIMEZONE)); $monthly ? $day->addMonthNoOverflow()->startOfMonth() : $day->addDay()) {
            $buckets[$day->format($format)] = ['label' => $day->format($monthly ? 'M Y' : 'M j'), 'orders' => 0, 'sales' => 0.0];
        }
        foreach ($rows->where('status', '!=', 'cancelled') as $row) {
            $key = $row['placed']?->copy()->timezone(self::TIMEZONE)->format($format);
            if ($key && $buckets->has($key)) {
                $bucket = $buckets[$key];
                $bucket['orders']++;
                $bucket['sales'] += $row['subtotal'];
                $buckets[$key] = $bucket;
            }
        }

        return $buckets->values();
    }

    /** Best-selling products in the period by quantity (cancelled orders left out). */
    public function topProducts(Collection $rows, int $limit = 8): Collection
    {
        $orderIds = $rows->where('status', '!=', 'cancelled')->pluck('order_id')->unique()->all();
        if (! $orderIds || ! Schema::hasTable('order_items')) {
            return collect();
        }
        $legacy = Order::usesLegacySchema();
        $query = DB::table('order_items');
        $legacy
            ? $query->whereIn('order_id', $orderIds)
            : $query->whereIn('seller_order_id', SellerOrder::whereIn('order_id', $orderIds)->pluck('id'));
        $amount = $legacy ? 'line_total' : 'unit_price_minor * quantity';

        return $query->selectRaw("product_name as name, sum(quantity) as quantity, sum({$amount}) as amount")
            ->groupBy('product_name')->orderByDesc('quantity')->limit($limit)->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'amount' => round((float) $row->amount / ($legacy ? 1 : 100), 2),
            ]);
    }

    /**
     * Records the 10% commission of every completed order that has none yet (deployed layout).
     * Returns how many were added.
     */
    public function recordMissingCommissions(): int
    {
        if (! Order::usesLegacySchema() || ! Schema::hasTable('commissions')) {
            return 0;
        }
        $missing = Order::whereIn('status', self::COMPLETED)->whereNotNull('seller_id')
            ->whereNotIn('id', DB::table('commissions')->select('order_id'))->get();
        foreach ($missing as $order) {
            DB::table('commissions')->insert([
                'order_id' => $order->id, 'seller_id' => $order->seller_id, 'rate' => self::rate() * 100,
                'amount' => round((float) $order->subtotal * self::rate(), 2), 'status' => 'earned',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $missing->count();
    }

    /** Marks earned commissions as settled (collected from the seller). */
    public function settle(array $commissionIds): int
    {
        if (! Schema::hasTable('commissions') || ! $commissionIds) {
            return 0;
        }

        return DB::table('commissions')->whereIn('id', $commissionIds)->where('status', 'earned')
            ->update(['status' => 'settled', 'settled_at' => now(), 'updated_at' => now()]);
    }

    private function legacyRows(?Carbon $from, ?Carbon $to): Collection
    {
        $orders = Order::query()
            ->with(['buyer:id,name', 'seller:id,name'])
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->orderBy('created_at')
            ->get();
        $commissions = Schema::hasTable('commissions')
            ? DB::table('commissions')->whereIn('order_id', $orders->pluck('id'))->get()->keyBy('order_id')
            : collect();
        $stores = $this->storeNames($orders->pluck('seller_id')->filter()->unique()->all());

        return $orders->map(function (Order $order) use ($commissions, $stores) {
            $record = $commissions[$order->id] ?? null;
            $subtotal = (float) $order->subtotal;
            [$amount, $state] = match (true) {
                $record !== null => [(float) $record->amount, $record->status === 'settled' ? 'settled' : 'earned'],
                $order->status === 'cancelled' => [0.0, 'none'],
                in_array($order->status, self::COMPLETED, true) => [round($subtotal * self::rate(), 2), 'unrecorded'],
                default => [round($subtotal * self::rate(), 2), 'pending'],
            };

            return [
                'order_id' => $order->id,
                'number' => (string) $order->order_number,
                'placed' => $order->placed_at ?? $order->created_at,
                'buyer' => $order->buyer?->name ?? 'Deleted account',
                'seller_id' => $order->seller_id ? (int) $order->seller_id : null,
                // Some early orders were saved before the seller was recorded on the order.
                'seller' => $stores[$order->seller_id] ?? ($order->seller?->name ?? 'No seller on record'),
                'status' => (string) $order->status,
                'subtotal' => $subtotal,
                'shipping' => (float) $order->shipping_fee,
                'total' => (float) $order->total,
                'commission' => $amount,
                'commission_status' => $state,
                'commission_id' => $record?->id,
            ];
        });
    }

    private function sellerOrderRows(?Carbon $from, ?Carbon $to): Collection
    {
        return SellerOrder::query()
            ->with(['order.buyer:id,name', 'seller'])
            ->whereHas('order', fn ($q) => $q
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to)))
            ->orderBy('id')
            ->get()
            ->map(function (SellerOrder $sellerOrder) {
                $status = (string) $sellerOrder->status;
                $commission = (int) $sellerOrder->commission_minor / 100 ?: round((int) $sellerOrder->subtotal_minor / 100 * self::rate(), 2);

                return [
                    'order_id' => $sellerOrder->order_id,
                    'number' => (string) $sellerOrder->order?->order_number,
                    'placed' => $sellerOrder->order?->created_at,
                    'buyer' => $sellerOrder->order?->buyer?->name ?? 'Deleted account',
                    'seller_id' => $sellerOrder->seller?->user_id,
                    'seller' => $sellerOrder->seller?->name ?? 'Unknown seller',
                    'status' => $status,
                    'subtotal' => (int) $sellerOrder->subtotal_minor / 100,
                    'shipping' => (int) $sellerOrder->shipping_fee_minor / 100,
                    'total' => ((int) $sellerOrder->subtotal_minor + (int) $sellerOrder->shipping_fee_minor) / 100,
                    'commission' => $status === 'cancelled' ? 0.0 : $commission,
                    'commission_status' => match (true) {
                        $status === 'cancelled' => 'none',
                        in_array($status, self::COMPLETED, true) => 'earned',
                        default => 'pending',
                    },
                    'commission_id' => null,
                ];
            });
    }

    /** Store names of sellers (deployed layout), keyed by user id. */
    private function storeNames(array $userIds): array
    {
        if (! $userIds || ! Schema::hasTable('seller_profiles')) {
            return [];
        }

        return DB::table('seller_profiles')->whereIn('user_id', $userIds)->pluck('store_name', 'user_id')->filter()->all();
    }

    public static function sellerName(User $seller): string
    {
        return Order::usesLegacySchema()
            ? (Schema::hasTable('seller_profiles') ? (DB::table('seller_profiles')->where('user_id', $seller->id)->value('store_name') ?: $seller->name) : $seller->name)
            : ($seller->sellers()->oldest('id')->value('name') ?: $seller->name);
    }
}
