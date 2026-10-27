<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a seller stands on ZAYLO over the last 30 days: the shop's place among all shops by sales, and its products'
 * places among all products by units sold. Cancelled orders do not count.
 */
class SellerRankings
{
    public const DAYS = 30;

    /** Shops up to this place are shown as a top shop; place 1 is the best shop. */
    public const TOP_SHOPS = 10;

    /** Products up to this place get a marketplace rank next to them. */
    public const TOP_PRODUCTS = 20;

    /**
     * @return array{shop: array{rank: ?int, shops: int, sales: float}, products: Collection<int, array{name: string, quantity: int, rank: ?int}>}
     */
    public function for(User $seller): array
    {
        $shopId = Shop::idForSeller($seller);
        $since = now()->subDays(self::DAYS);
        if (! $shopId || ! Schema::hasTable('order_items')) {
            return ['shop' => ['rank' => null, 'shops' => 0, 'sales' => 0.0], 'products' => collect()];
        }

        $shops = $this->shopSales($since);
        $sales = (float) ($shops[$shopId] ?? 0);
        $units = $this->productUnits($since);
        $mine = Product::where('seller_id', $shopId)->whereIn('id', $units->keys())->pluck('name', 'id');

        return [
            'shop' => [
                'rank' => $sales > 0 ? $this->place($shops, $sales) : null,
                'shops' => $shops->count(),
                'sales' => round($sales, 2),
            ],
            'products' => $mine->map(fn (string $name, int $id) => [
                'name' => $name,
                'quantity' => (int) $units[$id],
                'rank' => ($rank = $this->place($units, (float) $units[$id])) <= self::TOP_PRODUCTS ? $rank : null,
            ])->sortByDesc('quantity')->take(5)->values(),
        ];
    }

    /** Sales per shop id (what products.seller_id holds). */
    private function shopSales($since): Collection
    {
        $query = Order::usesLegacySchema()
            ? DB::table('orders')->whereNotNull('seller_id')->where('created_at', '>=', $since)
                ->selectRaw('seller_id as shop, SUM(subtotal) as total')
            : DB::table('seller_orders')->where('created_at', '>=', $since)
                ->selectRaw('seller_id as shop, SUM(subtotal_minor) / 100.0 as total');

        return $query->where('status', '!=', 'cancelled')->groupBy('shop')->pluck('total', 'shop')->map(fn ($total) => (float) $total);
    }

    /** Units sold per product id. */
    private function productUnits($since): Collection
    {
        $query = Order::usesLegacySchema()
            ? DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', '!=', 'cancelled')->where('orders.created_at', '>=', $since)
            : DB::table('order_items')->join('seller_orders', 'seller_orders.id', '=', 'order_items.seller_order_id')
                ->where('seller_orders.status', '!=', 'cancelled')->where('seller_orders.created_at', '>=', $since);

        return $query->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id as product, SUM(order_items.quantity) as units')
            ->pluck('units', 'product')->map(fn ($units) => (int) $units);
    }

    /** 1 plus the number of entries ahead; ties share a place. */
    private function place(Collection $totals, float $value): int
    {
        return 1 + $totals->filter(fn ($total) => $total > $value)->count();
    }
}
