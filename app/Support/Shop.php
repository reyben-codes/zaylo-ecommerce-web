<?php

namespace App\Support;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Seller;
use App\Models\SellerApplication;
use App\Models\ShopFollow;
use App\Models\ShopSection;
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
    /** How the Products tab can be ordered. */
    public const SORTS = ['newest' => 'Newest', 'popular' => 'Best selling', 'price_low' => 'Price: low to high', 'price_high' => 'Price: high to low'];

    /** How the Reviews tab can rank the shop's products. */
    public const RANKINGS = ['highest_rated' => 'Highest rating', 'lowest_rated' => 'Lowest rating', 'best_selling' => 'Best selling', 'least_selling' => 'Least selling'];

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

    /**
     * Open shops whose name contains the words, names starting with them first.
     *
     * @return Collection<int, self>
     */
    public static function search(string $term, int $limit = 6): Collection
    {
        $term = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $term)));
        if ($term === '') {
            return collect();
        }
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        if (Product::usesLegacySchema()) {
            $table = Schema::hasTable('seller_profiles') ? 'seller_profiles' : 'sellers';
            $column = $table === 'seller_profiles' ? 'store_name' : 'name';
            $matches = DB::table($table)->selectRaw("{$table}.user_id as shop_id");
        } else {
            $table = 'sellers';
            $column = 'name';
            $matches = DB::table($table)->where('status', 'approved')->selectRaw('sellers.id as shop_id');
        }

        // Extra candidates cover shops that are closed or suspended now.
        return $matches->whereRaw("LOWER({$table}.{$column}) LIKE ? ESCAPE '!'", ["%{$escaped}%"])
            ->orderByRaw("CASE WHEN LOWER({$table}.{$column}) LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", ["{$escaped}%"])
            ->orderBy("{$table}.{$column}")->limit($limit * 3)->pluck('shop_id')
            ->map(fn ($id) => self::find((int) $id))->filter()->take($limit)->values();
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

    /**
     * The shop's products with what cards need, filtered and ordered by one of SORTS or RANKINGS. Products without
     * reviews come last in both rating orders; units_sold counts the same way as soldCount().
     */
    public function listing(?string $search, ?string $category, string $sort, ?ShopSection $section = null): Builder
    {
        $query = $this->products()->withReviewSummary()->with(Product::usesLegacySchema() ? ['images', 'additionalCategories'] : ['images', 'additionalCategories', 'defaultVariant', 'category'])
            ->withCount(['variants as active_variants_count' => fn ($variant) => $variant->where('is_active', true)])
            ->withSum(['variants as catalog_variant_stock' => fn ($variant) => $variant->where('is_active', true)], 'stock')
            ->withSum(['orderItems as units_sold' => fn ($item) => Order::usesLegacySchema()
                ? $item->whereIn('order_id', DB::table('orders')->whereIn('status', ['delivered', 'completed'])->select('id'))
                : $item->whereIn('seller_order_id', DB::table('seller_orders')->whereIn('status', ['delivered', 'completed'])->select('id'))], 'quantity');

        if ($search) {
            $pattern = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
            $query->where(fn ($match) => $match->whereRaw('LOWER(products.name) LIKE ?', [$pattern])->orWhereRaw('LOWER(products.description) LIKE ?', [$pattern]));
        }
        if ($category) {
            $query->inCategories([$category]);
        }
        if ($section) {
            $query->whereIn('products.id', DB::table('product_shop_section')->where('shop_section_id', $section->id)->select('product_id'));
        }
        $unrated = 'NOT EXISTS (SELECT 1 FROM product_reviews WHERE product_reviews.product_id = products.id)';
        match ($sort) {
            'price_low', 'price_high' => $query->orderByRaw(Product::sellingPriceSql().($sort === 'price_high' ? ' DESC' : ' ASC'), [now(), now()]),
            'popular', 'best_selling' => $query->orderByDesc('units_sold')->latest('products.created_at'),
            'least_selling' => $query->orderBy('units_sold')->latest('products.created_at'),
            'highest_rated' => $query->orderByRaw($unrated)->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count'),
            'lowest_rated' => $query->orderByRaw($unrated)->orderBy('reviews_avg_rating')->orderByDesc('reviews_count'),
            default => $query->latest('products.created_at'),
        };

        return $query->orderByDesc('products.id');
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
        // The order tables decide where sales are kept; products can be on the other layout.
        if (Order::usesLegacySchema()) {
            return (int) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.status', ['delivered', 'completed'])
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
        $sales = Order::usesLegacySchema()
            ? DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereIn('orders.status', ['delivered', 'completed'])
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

    /**
     * Categories the shop's visible products are listed under, with how many each one has.
     *
     * @return array<string, array{label: string, count: int}>
     */
    /**
     * The seller's own sections buyers can browse: shown, and holding at least one product buyers can see, in the
     * seller's order, each with product_count.
     *
     * @return Collection<int, ShopSection>
     */
    public function sections(): Collection
    {
        if (! ShopSection::available()) {
            return collect();
        }

        return ShopSection::forShop($this->id)->where('is_visible', true)
            ->withCount(['products as product_count' => fn (Builder $products) => $products->visibleToBuyers()->notArchived()])
            ->get()->filter(fn (ShopSection $section) => $section->product_count > 0)->values();
    }

    /** One section buyers can browse, or null when it is hidden, empty or another shop's. */
    public function section(?int $id): ?ShopSection
    {
        return $id ? $this->sections()->firstWhere('id', $id) : null;
    }

    public function categoryCounts(): array
    {
        $counts = $this->products()->with(Product::usesLegacySchema() ? ['additionalCategories'] : ['category', 'additionalCategories'])
            ->get()->flatMap(fn (Product $product) => collect($product->category_keys)->unique())->countBy();

        return collect(config('marketplace.categories'))->only($counts->keys()->all())
            ->map(fn (string $label, string $key) => ['label' => $label, 'count' => (int) $counts[$key]])->all();
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
