<?php

namespace App\Support;

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\ShopFollow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A seller's public shop, the same on both schemas. Its id is what products.seller_id holds: the seller's user id on
 * the deployed schema (shop name in seller_profiles), or the sellers row on the newer one.
 */
class Shop
{
    public function __construct(
        public readonly int $id,
        public readonly User $owner,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $location,
        public readonly ?Carbon $joined,
    ) {}

    /** The shop if it is open to the public: an active, verified seller (and an approved shop on the newer schema). */
    public static function find(int $id): ?self
    {
        if (Product::usesLegacySchema()) {
            $owner = User::with('sellerProfile')->find($id);
            if (! $owner || ! $owner->hasRole('seller') || ! $owner->isActive()) {
                return null;
            }

            return new self($id, $owner, $owner->sellerStoreName(), null, self::applicationLocation($owner), $owner->approved_at ?? $owner->created_at);
        }

        $shop = Seller::with(['owner', 'pickupAddress'])->find($id);
        $owner = $shop?->owner;
        if (! $shop || ! $owner || $shop->status !== 'approved' || ! $owner->isActive()) {
            return null;
        }
        $address = $shop->pickupAddress;
        $location = $address ? collect([$address->city, $address->province])->filter()->implode(', ') : null;

        return new self($shop->id, $owner, $shop->name, $shop->description, $location ?: self::applicationLocation($owner), $shop->created_at);
    }

    /** The shop id a seller account owns (for its dashboard), without creating a shop. */
    public static function idForSeller(User $seller): ?int
    {
        if (Product::usesLegacySchema()) {
            return $seller->id;
        }

        return Seller::where('user_id', $seller->id)->oldest('id')->value('id');
    }

    public function url(): string
    {
        return route('shops.show', $this->id);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') ?: 'Z';
    }

    /** Products buyers can see: active and not archived. */
    public function products(): Builder
    {
        return Product::where('products.seller_id', $this->id)->visibleToBuyers()->notArchived();
    }

    public function followerCount(): int
    {
        return self::followersOf($this->id);
    }

    public static function followersOf(int $shopId): int
    {
        return Schema::hasTable('shop_follows') ? ShopFollow::where('shop_id', $shopId)->count() : 0;
    }

    public function isFollowedBy(?User $user): bool
    {
        return $user !== null && Schema::hasTable('shop_follows')
            && ShopFollow::where('user_id', $user->id)->where('shop_id', $this->id)->exists();
    }

    /** Average rating and number of reviews across everything the shop has sold. */
    public function rating(): array
    {
        $reviews = ProductReview::whereIn('product_id', Product::where('seller_id', $this->id)->select('id'));

        return ['average' => ($count = (clone $reviews)->count()) ? round((float) (clone $reviews)->avg('rating'), 1) : null, 'count' => $count];
    }

    /** Units delivered to buyers. */
    public function soldCount(): int
    {
        if (Product::usesLegacySchema()) {
            return (int) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', 'completed')
                ->whereIn('order_items.product_id', Product::where('seller_id', $this->id)->select('id'))
                ->sum('order_items.quantity');
        }

        return (int) DB::table('order_items')->join('seller_orders', 'seller_orders.id', '=', 'order_items.seller_order_id')
            ->where('seller_orders.seller_id', $this->id)->whereIn('seller_orders.status', ['delivered', 'completed'])
            ->sum('order_items.quantity');
    }

    /**
     * Open shops with the most units delivered, best first, each with its sold count, rating, followers and a few
     * product photos. Counts the same way as soldCount().
     *
     * @return Collection<int, array{shop: self, sold: int, rating: array, followers: int, preview: Collection}>
     */
    public static function topSelling(int $limit = 4): Collection
    {
        $sales = Product::usesLegacySchema()
            ? DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('orders.status', 'completed')
                ->selectRaw('products.seller_id as shop_id, SUM(order_items.quantity) as sold')->groupBy('products.seller_id')
            : DB::table('order_items')->join('seller_orders', 'seller_orders.id', '=', 'order_items.seller_order_id')
                ->whereIn('seller_orders.status', ['delivered', 'completed'])
                ->selectRaw('seller_orders.seller_id as shop_id, SUM(order_items.quantity) as sold')->groupBy('seller_orders.seller_id');

        // Extra candidates cover shops that are closed or suspended now.
        return $sales->orderByDesc('sold')->limit($limit * 3)->get()
            ->map(fn ($row) => ($shop = self::find((int) $row->shop_id)) ? ['shop' => $shop, 'sold' => (int) $row->sold] : null)
            ->filter()->take($limit)
            ->map(fn (array $row) => $row + [
                'rating' => $row['shop']->rating(),
                'followers' => $row['shop']->followerCount(),
                'preview' => $row['shop']->products()->with('images')->latest()->limit(3)->get(),
            ])->values();
    }

    /** Categories the shop's visible products are listed under, for the shop's filter. */
    public function categories(): array
    {
        $keys = $this->products()->with(Product::usesLegacySchema() ? ['additionalCategories'] : ['category', 'additionalCategories'])
            ->get()->flatMap(fn (Product $product) => $product->category_keys)->unique();
        $labels = config('marketplace.categories');

        return collect($labels)->only($keys->all())->all();
    }

    private static function applicationLocation(User $owner): ?string
    {
        if (! Schema::hasTable('seller_applications')) {
            return null;
        }
        $application = SellerApplication::where('user_id', $owner->id)->first();
        $location = $application ? collect([$application->city_name, $application->province_name ?: $application->region_name])->filter()->implode(', ') : null;

        return $location ?: (Address::where('user_id', $owner->id)->orderByDesc('is_default')->first()?->city ?: null);
    }
}
