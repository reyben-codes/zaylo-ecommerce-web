<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Services\RiderDeliveries;
use App\Services\SellerApplicationService;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    use UpdatesAccountSettings;

    public const COMMISSION_RATE = 0.10;

    private const REPORT_TIMEZONE = 'Asia/Manila';

    private const ORDER_TRANSITIONS = [
        'placed' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready_for_pickup'],
        'ready_for_pickup' => ['picked_up'],
        'picked_up' => ['in_transit'],
        'in_transit' => ['out_for_delivery'],
        'out_for_delivery' => ['completed'],
    ];

    private const DELIVERY_STATUSES = [
        'ready_for_pickup' => 'ready',
        'picked_up' => 'picked_up',
        'in_transit' => 'in_transit',
        'out_for_delivery' => 'out_for_delivery',
        'completed' => 'delivered',
    ];

    private const ORDER_STATUS_GROUPS = [
        'to_prepare' => ['placed', 'confirmed', 'processing'],
        'shipping' => ['ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery'],
        'delivered' => ['completed'],
        'cancelled' => ['cancelled'],
    ];

    // Status names used by the normalized seller_orders table.
    private const SELLER_ORDER_STATUS_GROUPS = [
        'to_prepare' => ['pending', 'accepted', 'packed'],
        'shipping' => ['ready_to_ship', 'shipped'],
        'delivered' => ['delivered', 'completed'],
        'cancelled' => ['cancelled'],
    ];

    // A product shows its main photo plus up to this many more; each save uploads at most PHOTOS_PER_UPLOAD
    // so a full form stays under the server's 40 MB post limit.
    private const MAX_EXTRA_PHOTOS = 9;
    private const PHOTOS_PER_UPLOAD = 6;

    public const STATUS_LABELS = [
        'placed' => 'Placed',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready to ship',
        'picked_up' => 'Shipped',
        'in_transit' => 'In transit',
        'out_for_delivery' => 'Out for delivery',
        'completed' => 'Delivered',
        'cancelled' => 'Cancelled',
        'pending' => 'Placed',
        'accepted' => 'Confirmed',
        'packed' => 'Processing',
        'ready_to_ship' => 'Ready to ship',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
    ];

    public function dashboard()
    {
        $shopId = $this->shopId();
        $products = Product::where('seller_id', $shopId);
        $orders = $this->orderSummary($shopId);

        return view('seller.dashboard', [
            'user' => auth()->user(),
            'storeName' => $this->storeName(),
            'productCount' => (clone $products)->count(),
            'activeProductCount' => (clone $products)->where('is_active', true)->count(),
            'lowStockCount' => $this->whereStock(clone $products, 'low')->count(),
            'outOfStockCount' => $this->whereStock(clone $products, 'out')->count(),
            'restockProducts' => $this->withStockDetails(
                (clone $products)->where(fn (Builder $query) => $this->whereStock($query, 'low')
                    ->orWhere(fn (Builder $nested) => $this->whereStock($nested, 'out')))
            )->orderBy('name')->limit(5)->get()->map(fn (Product $product) => [
                'name' => $product->name,
                'category' => $product->category_label,
                'stock' => $this->stockState($product)['total'],
            ]),
            'orderCounts' => $orders['counts'],
            'revenue' => $orders['revenue'],
            'monthRevenue' => $orders['monthRevenue'],
            'recentOrders' => $orders['recent'],
            'rating' => $this->ratingSummary($shopId),
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    public function storeProduct(Request $request)
    {
        $options = $this->newProductOptions($request);
        if ($options !== []) {
            // With options, the listing price is the cheapest option and stock is their total.
            $request->merge(['price' => min(array_column($options, 'price')), 'stock' => array_sum(array_column($options, 'stock'))]);
        }
        $data = $this->validatedProduct($request);
        $data = $this->storeProductImage($request, $data);
        $extraCategories = Arr::pull($data, 'additional_categories') ?? [];
        $product = DB::transaction(function () use ($data, $options, $request, $extraCategories) {
            $product = Product::create($data + ['seller_id' => $this->shopId(), 'is_active' => $request->boolean('is_active', true)]);
            $product->syncAdditionalCategories($extraCategories);
            $this->saveExtraPhotos($request, $product);
            $this->createProductOptions($product, $options);

            return $product;
        });

        $count = count($options);

        return redirect()->route('seller.products.edit', $product)->with('status', $count
            ? 'Product created with '.$count.' '.Str::plural('option', $count).'. You can change sizes, prices and stock below anytime.'
            : 'Product created. You can schedule a sale or add colors and sizes below.');
    }

    public function updateProduct(Request $request, Product $product)
    {
        $this->authorize('update', $product);
        $data = $this->validatedProduct($request, $product);
        $data = $this->storeProductImage($request, $data, $product);
        $extraCategories = Arr::pull($data, 'additional_categories') ?? [];
        $removePhotos = array_map('intval', Arr::pull($data, 'remove_photos') ?? []);
        DB::transaction(function () use ($product, $data, $request, $extraCategories, $removePhotos) {
            $product->update($data + ['is_active' => $request->boolean('is_active')]);
            $product->syncAdditionalCategories($extraCategories);
            $this->saveExtraPhotos($request, $product, $removePhotos);
        });

        return back()->with('status', 'Product updated successfully.');
    }

    public function deleteProduct(Product $product)
    {
        $this->authorize('delete', $product);
        $product->delete();

        return redirect()->route('seller.products')->with('status', 'Product removed. Existing order records were preserved.');
    }

    public function orders(Request $request)
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'to_prepare', 'shipping', 'delivered', 'cancelled'])],
        ])['status'] ?? 'all';
        $statusLabels = self::STATUS_LABELS;

        if (! Order::usesLegacySchema()) {
            // Checkout writes the per-seller order table; the normalized schema has no fulfilment list yet.
            $counts = array_fill_keys(['all', ...array_keys(self::ORDER_STATUS_GROUPS)], 0);
            $orders = new LengthAwarePaginator([], 0, 12, 1, ['path' => $request->url()]);

            return view('seller.orders', compact('orders', 'filter', 'counts', 'statusLabels'));
        }

        $baseQuery = Order::where('seller_id', $this->shopId());
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts = ['all' => $statusCounts->sum()];

        foreach (self::ORDER_STATUS_GROUPS as $group => $statuses) {
            $counts[$group] = collect($statuses)->sum(fn ($status) => (int) $statusCounts->get($status, 0));
        }

        $orders = $baseQuery
            ->with(['buyer', 'items', 'payment', 'shipment', 'statusHistory' => fn ($query) => $query->oldest()])
            ->when($filter !== 'all', fn ($query) => $query->whereIn('status', self::ORDER_STATUS_GROUPS[$filter]))
            ->latest('placed_at')
            ->paginate(12)
            ->withQueryString();

        return view('seller.orders', compact('orders', 'filter', 'counts', 'statusLabels'));
    }

    /** A printable label for the parcel; the rider scans its QR code (the tracking number) to deliver it. */
    public function parcelLabel(Order $order)
    {
        abort_unless((int) $order->seller_id === $this->shopId(), 403);
        $order->load(['shipment', 'items']);
        abort_unless($order->shipment, 404, 'Mark the order ready to ship to get its parcel label.');
        $code = RiderDeliveries::labelCode($order->shipment);

        return view('seller.parcel-label', [
            'order' => $order,
            'code' => $code,
            'store' => $this->storeName(),
            'qr' => (new QRCode(new QROptions(['outputBase64' => false, 'addQuietzone' => true])))->render($code),
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        abort_unless((int) $order->seller_id === $this->shopId(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'processing', 'ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'completed', 'cancelled'])],
        ]);

        DB::transaction(function () use ($order, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($data['status'], self::ORDER_TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'That order status transition is not allowed.']);
            }
            if (! config('marketplace.seller_managed_delivery')
                && in_array($data['status'], ['picked_up', 'in_transit', 'out_for_delivery', 'completed'], true)) {
                throw ValidationException::withMessages(['status' => 'The assigned logistics provider must update this delivery.']);
            }

            if ($data['status'] === 'cancelled') {
                $locked->load(['items', 'payment', 'shipment']);
                foreach ($locked->items as $item) {
                    $item->product_variant_id
                        ? ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->quantity)
                        : Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                    Product::syncStockFromVariants((int) $item->product_id);
                }
                $locked->payment?->update(['status' => 'cancelled']);
                $locked->shipment?->update(['status' => 'cancelled']);
            }

            $orderAttributes = ['status' => $data['status']];
            if ($data['status'] === 'completed') {
                $orderAttributes['completed_at'] = now();
            }
            $locked->update($orderAttributes);
            $locked->statusHistory()->create([
                'changed_by' => auth()->id(),
                'status' => $data['status'],
                'note' => in_array($data['status'], ['picked_up', 'in_transit', 'out_for_delivery', 'completed'], true)
                    ? 'Updated by seller in self-managed delivery mode.'
                    : 'Updated by seller.',
            ]);

            if (isset(self::DELIVERY_STATUSES[$data['status']])) {
                $shipment = $locked->shipment()->firstOrCreate([], [
                    'tracking_number' => 'TRK-'.Str::upper(Str::random(12)),
                    'status' => 'ready',
                ]);
                $shipmentAttributes = ['status' => self::DELIVERY_STATUSES[$data['status']]];
                if ($data['status'] === 'picked_up' && ! $shipment->picked_up_at) {
                    $shipmentAttributes['picked_up_at'] = now();
                }
                if ($data['status'] === 'completed') {
                    $shipmentAttributes['delivered_at'] = now();
                }
                $shipment->update($shipmentAttributes);
            }

            if ($data['status'] === 'completed') {
                $locked->payment?->update(['status' => 'paid', 'paid_at' => now()]);
                if (Schema::hasTable('commissions')) {
                    DB::table('commissions')->updateOrInsert(
                        ['order_id' => $locked->id],
                        [
                            'seller_id' => $locked->seller_id,
                            'rate' => self::COMMISSION_RATE * 100,
                            'amount' => round((float) $locked->subtotal * self::COMMISSION_RATE, 2),
                            'status' => 'earned',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });

        $message = match ($data['status']) {
            'picked_up' => 'Parcel marked as shipped.',
            'in_transit' => 'Parcel marked as in transit.',
            'out_for_delivery' => 'Parcel marked as out for delivery.',
            'completed' => 'Order marked as delivered and completed.',
            'cancelled' => 'Order cancelled and stock restored.',
            default => 'Order status updated.',
        };

        return back()->with('status', $message);
    }

    /**
     * Stock overview: summary cards, filters, and quick restocking.
     */
    public function inventory(Request $request)
    {
        $filters = $this->productFilters($request, withStock: true);
        $base = Product::where('seller_id', $this->shopId());
        $summary = [
            'total' => (clone $base)->count(),
            'in' => $this->whereStock(clone $base, 'in')->count(),
            'low' => $this->whereStock(clone $base, 'low')->count(),
            'out' => $this->whereStock(clone $base, 'out')->count(),
        ];
        $products = $this->filteredProducts($filters)->orderBy('name')->paginate(20)->withQueryString();

        return view('seller.inventory', [
            'products' => $products,
            'summary' => $summary,
            'filters' => $filters,
            'categories' => config('marketplace.categories'),
            'stockStates' => $this->stockStatesFor($products),
            'legacy' => Product::usesLegacySchema(),
        ]);
    }

    /**
     * Catalog: every product as a card, each opening its full edit page.
     */
    public function products(Request $request)
    {
        $filters = $this->productFilters($request, withStock: false);
        $products = $this->filteredProducts($filters)->latest()->paginate(12)->withQueryString();

        return view('seller.products', [
            'products' => $products,
            'filters' => $filters,
            'categories' => config('marketplace.categories'),
            'stockStates' => $this->stockStatesFor($products),
            'storeName' => $this->storeName(),
            'total' => Product::where('seller_id', $this->shopId())->count(),
        ]);
    }

    public function createProduct()
    {
        return view('seller.product-form', [
            'product' => null,
            'categories' => config('marketplace.categories'),
            'legacy' => Product::usesLegacySchema(),
        ]);
    }

    public function editProduct(Product $product)
    {
        $this->authorize('update', $product);
        $product = $this->withStockDetails(Product::whereKey($product->id))->firstOrFail();

        return view('seller.product-form', [
            'product' => $product,
            'categories' => config('marketplace.categories'),
            'legacy' => Product::usesLegacySchema(),
            'stockState' => $this->stockState($product),
        ]);
    }

    public function updateStock(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        // A product without options only has a stock count, until the seller gives it sizes or colors.
        if (Product::usesLegacySchema() && ! $product->variants()->where('is_active', true)->exists()
            && ! $request->filled('new') && ! $request->filled('added_color') && ! $request->filled('colors')) {
            $data = $request->validate(['stock' => 'required|integer|min:0|max:1000000']);
            $product->update(['stock' => $data['stock']]);

            return back()->with('status', "Stock updated for {$product->name}.");
        }

        // Stock, price and size per variation, sizes added to or removed from a color, and colors named or added.
        $data = $request->validate([
            'variants' => 'sometimes|array',
            'variants.*' => 'required|integer|min:0|max:1000000',
            'prices' => 'sometimes|array',
            'prices.*' => 'required|numeric|min:0|max:99999999.99',
            'sizes' => 'sometimes|array',
            'sizes.*' => 'nullable|string|max:40',
            'remove' => 'sometimes|array',
            'remove.*' => 'integer',
            'new' => 'sometimes|array|max:50',
            'new.*.color' => 'nullable|string|max:80',
            'new.*.size' => 'nullable|string|max:40',
            'new.*.price' => 'nullable|required_with:new.*.size|numeric|min:0|max:99999999.99',
            'new.*.stock' => 'nullable|required_with:new.*.size|integer|min:0|max:1000000',
            'colors' => 'sometimes|array|max:50',
            'colors.*.from' => 'nullable|string|max:80',
            'colors.*.to' => 'nullable|string|max:40',
            'added_color' => 'nullable|string|max:40',
            'added' => 'sometimes|array|max:50',
            'added.*.size' => 'nullable|string|max:40',
            'added.*.price' => 'nullable|numeric|min:0|max:99999999.99',
            'added.*.stock' => 'nullable|integer|min:0|max:1000000',
        ]);
        $legacy = Product::usesLegacySchema();
        $active = $this->forSale($product->variants())->orderBy('id')->get()->keyBy('id');
        $ids = array_unique(array_merge(
            array_keys($data['variants'] ?? []), array_keys($data['prices'] ?? []), array_keys($data['sizes'] ?? []), $data['remove'] ?? [],
        ));
        if (array_diff($ids, $active->keys()->all())) {
            throw ValidationException::withMessages(['variants' => 'One of those variations no longer belongs to this product.']);
        }
        $colors = $active->map->color_label->filter()->unique();

        // Each color card can be renamed; the unnamed list ('') can be given its first color. Only real changes are kept.
        $renames = collect($data['colors'] ?? [])
            ->mapWithKeys(fn ($row) => [trim((string) ($row['from'] ?? '')) => filled($row['to'] ?? null) ? Str::squish($row['to']) : null])
            ->filter(fn ($to, $from) => $to !== ($from === '' ? null : $from));
        if ($renames->keys()->contains(fn ($from) => $from !== '' && ! $colors->contains($from))) {
            throw ValidationException::withMessages(['variants' => 'One of those colors no longer belongs to this product.']);
        }
        $colorFor = fn (?string $color) => $renames->has($color ?? '') ? $renames->get($color ?? '') : $color;

        $additions = collect($data['new'] ?? [])->filter(fn ($row) => filled(trim((string) ($row['size'] ?? ''))))
            ->map(fn ($row) => ['from' => filled($row['color'] ?? null) ? trim($row['color']) : null, 'size' => trim($row['size']),
                'price' => (float) $row['price'], 'stock' => (int) $row['stock']])->values();
        if ($additions->contains(fn ($row) => $row['from'] !== null && ! $colors->contains($row['from']))) {
            throw ValidationException::withMessages(['variants' => 'Sizes can only be added to this product\'s existing colors.']);
        }
        $additions = $additions->map(fn ($row) => ['color' => $colorFor($row['from'])] + $row);

        // Work out the result first so a bad combination saves nothing.
        $removed = collect($data['remove'] ?? [])->map(fn ($id) => (int) $id);
        $remaining = $active->reject(fn ($variant) => $removed->contains($variant->id));

        // A brand-new color starts with the sizes typed for it, or as a single option when it has none.
        $addedColor = filled($data['added_color'] ?? null) ? Str::squish($data['added_color']) : null;
        $addedRows = collect($data['added'] ?? [])->values();
        $addedSized = $addedRows->filter(fn ($row) => filled(trim((string) ($row['size'] ?? ''))));
        if ($addedColor === null && $addedSized->isNotEmpty()) {
            throw ValidationException::withMessages(['added_color' => 'Name the new color before adding its sizes.']);
        }
        if ($addedColor !== null) {
            $taken = $remaining->map(fn ($variant) => $colorFor($variant->color_label))->concat($additions->pluck('color'))->filter();
            if ($taken->contains(fn ($color) => Str::lower($color) === Str::lower($addedColor))) {
                throw ValidationException::withMessages(['added_color' => "{$addedColor} is already one of this product's colors. Add its sizes in that color's card."]);
            }
            $startRows = $addedSized->isNotEmpty() ? $addedSized : collect([$addedRows->first() ?? []]);
            $additions = $additions->concat($startRows->map(fn ($row) => [
                'from' => null, 'color' => $addedColor, 'size' => filled(trim((string) ($row['size'] ?? ''))) ? trim($row['size']) : null,
                'price' => filled($row['price'] ?? null) ? (float) $row['price'] : (float) $product->regular_price,
                'stock' => (int) ($row['stock'] ?? 0),
            ])->values());
        }
        // On the deployed schema a product without options keeps its stock on the product; naming its color turns that into an option.
        if ($legacy && $active->isEmpty() && $colorFor(null) !== null && ! $additions->contains('color', $colorFor(null))) {
            $additions->push(['from' => null, 'color' => $colorFor(null), 'size' => null, 'price' => (float) $product->regular_price, 'stock' => (int) $product->stock]);
        }
        if ($legacy && $active->isEmpty() && (int) $product->stock > 0 && $colorFor(null) === null && $additions->contains(fn ($row) => $row['color'] !== null)) {
            throw ValidationException::withMessages(['colors' => 'Name this product\'s current color first, so its existing stock keeps a color.']);
        }
        if ($ids === [] && $additions->isEmpty() && $renames->isEmpty()) {
            throw ValidationException::withMessages(['variants' => 'Add a size or color, or change a variation before saving.']);
        }

        // A simple product's single unnamed option becomes its first size (in the color it was given) instead of sitting beside it.
        $first = $active->first();
        $placeholderIndex = ! $legacy && $active->count() === 1 && $first->color_label === null && $first->size_label === null
            && blank($data['sizes'][$first->id] ?? null) && ! $removed->contains($first->id)
            ? $additions->search(fn ($row) => $row['color'] === $colorFor(null) && $row['size'] !== null) : false;
        $placeholder = $placeholderIndex === false ? null : $first;
        $rows = $remaining->reject(fn ($variant) => $variant->is($placeholder))->map(fn ($variant) => [
            'color' => $colorFor($variant->color_label),
            'size' => array_key_exists($variant->id, $data['sizes'] ?? []) ? (filled($data['sizes'][$variant->id]) ? trim($data['sizes'][$variant->id]) : null) : $variant->size_label,
        ])->values()->concat($additions->map(fn ($row) => ['color' => $row['color'], 'size' => $row['size']]));
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['variants' => 'Keep at least one size or option for sale. Hide the product instead to stop selling it.']);
        }
        $names = $rows->pluck('color')->filter()->unique();
        if ($names->groupBy(fn ($color) => Str::lower($color))->contains(fn ($same) => $same->count() > 1)) {
            throw ValidationException::withMessages(['colors' => 'Two colors differ only in capital letters. Spell them the same to combine them, or give them different names.']);
        }
        // Buyers can't pick an unnamed option beside named colors, so a newly colored product needs every option named.
        $alreadyMixed = $colors->isNotEmpty() && $active->contains(fn ($variant) => $variant->color_label === null);
        if (! $alreadyMixed && $names->isNotEmpty() && $rows->contains(fn ($row) => $row['color'] === null)) {
            throw ValidationException::withMessages(['colors' => 'Name the color of the options you already have too, so buyers can tell the colors apart.']);
        }
        foreach ($rows->groupBy(fn ($row) => $row['color'] ?? '') as $color => $group) {
            $label = $color !== '' ? $color : 'this product';
            if ($group->count() > 1 && $group->contains(fn ($row) => $row['size'] === null)) {
                throw ValidationException::withMessages(['variants' => "Give every option of {$label} a size, or keep just one."]);
            }
            if ($group->pluck('size')->filter()->map(fn ($size) => Str::lower($size))->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['variants' => "Each size can only be listed once for {$label}."]);
            }
        }

        DB::transaction(function () use ($product, $active, $data, $additions, $removed, $placeholder, $placeholderIndex, $renames, $colorFor, $legacy) {
            $priceColumn = fn (float $price) => $legacy ? ['price' => round($price, 2)] : ['price_minor' => (int) round($price * 100)];
            $optionColumns = fn (?ProductVariant $variant, ?string $color, ?string $size) => ($legacy
                ? ['color' => $color, 'size' => $size]
                : ['options' => array_filter(array_merge($variant?->options ?? [], ['color' => $color, 'size' => $size]), fn ($value) => filled($value))])
                + ['name' => ProductVariant::nameFor($color, $size)];

            foreach ($active as $variant) {
                if ($removed->contains($variant->id)) {
                    \App\Models\CartItem::where('product_variant_id', $variant->id)->delete();
                    // Past orders point at the option, so keep it hidden rather than deleting it.
                    OrderItem::where('product_variant_id', $variant->id)->exists()
                        ? $variant->update(['is_active' => false, 'stock' => 0])
                        : $variant->delete();
                    continue;
                }
                $changes = isset($data['variants'][$variant->id]) ? ['stock' => (int) $data['variants'][$variant->id]] : [];
                if (isset($data['prices'][$variant->id])) {
                    $changes += $priceColumn((float) $data['prices'][$variant->id]);
                }
                $size = array_key_exists($variant->id, $data['sizes'] ?? [])
                    ? (filled($data['sizes'][$variant->id]) ? trim($data['sizes'][$variant->id]) : null)
                    : $variant->size_label;
                $color = $colorFor($variant->color_label);
                if ($size !== $variant->size_label || $color !== $variant->color_label) {
                    $changes += $optionColumns($variant, $color, $size);
                }
                $variant->update($changes);
            }

            foreach ($additions as $index => $row) {
                if ($placeholder && $index === $placeholderIndex) {
                    $placeholder->update($optionColumns($placeholder, $row['color'], $row['size']) + $priceColumn($row['price']) + ['stock' => $row['stock']]);
                    continue;
                }
                $base = $active->first(fn ($variant) => $colorFor($variant->color_label) === $row['color']);
                $variant = new ProductVariant(['product_id' => $product->id, 'is_active' => true, 'stock' => $row['stock']]
                    + $priceColumn($row['price']) + ['sku' => $this->variantSku($base?->sku ?? $product->sku, $row['size'] ?? (string) $row['color'])]);
                $variant->fill($optionColumns(null, $row['color'], $row['size'])
                    + ($legacy ? [] : ['low_stock_threshold' => Product::lowStockThreshold()]));
                $variant->save();
            }

            // Color photos are linked by "<product> — <color>" alt text, so they follow a renamed color.
            foreach ($renames as $from => $to) {
                if ($from === '') {
                    continue;
                }
                $product->images()->get()->filter(fn ($image) => Str::endsWith((string) $image->alt_text, ' — '.$from))
                    ->each(fn ($image) => $image->update(['alt_text' => $to === null ? $product->name : Str::beforeLast($image->alt_text, ' — '.$from).' — '.$to]));
            }

            Product::syncStockFromVariants($product->id);
            if ($legacy && $product->variants()->where('is_active', true)->exists()) {
                // Listings show the product price; use the lowest option price so "from" pricing is honest.
                $product->update(['price' => $product->variants()->where('is_active', true)->min('price')]);
            }
        });

        return back()->with('status', "Colors, sizes and stock updated for {$product->name}.");
    }

    /**
     * Colors and sizes entered on the add-product form, as one row per option: [color, size, price, stock].
     * A color without sizes is sold as a single option; an unnamed list holds sizes only. Empty when none were entered.
     */
    private function newProductOptions(Request $request): array
    {
        $request->validate([
            'options' => 'sometimes|array|max:20',
            'options.*.color' => 'nullable|string|max:40',
            'options.*.sizes' => 'sometimes|array|max:30',
            'options.*.sizes.*.size' => 'nullable|string|max:40',
            'options.*.sizes.*.price' => 'nullable|numeric|min:0|max:99999999.99',
            'options.*.sizes.*.stock' => 'nullable|integer|min:0|max:1000000',
        ], [
            'options.max' => 'Add up to 20 colors. You can add more after saving.',
            'options.*.color.max' => 'Keep each color name to 40 characters or fewer.',
            'options.*.sizes.max' => 'Add up to 30 sizes per color.',
            'options.*.sizes.*.size.max' => 'Keep each size to 40 characters or fewer.',
            'options.*.sizes.*.price.*' => 'Enter a price from 0 for every size.',
            'options.*.sizes.*.stock.*' => 'Enter a whole-number stock from 0 for every size.',
        ]);

        $groups = collect($request->input('options', []))->map(function ($group) {
            $color = filled($group['color'] ?? null) ? Str::squish($group['color']) : null;
            $rows = collect($group['sizes'] ?? [])->values();
            $sized = $rows->filter(fn ($row) => filled(trim((string) ($row['size'] ?? ''))));
            if ($sized->isNotEmpty()) {
                $rows = $sized->map(fn ($row) => ['size' => Str::squish($row['size'])] + $row);
            } else {
                // A named color with no sizes is one option, priced and stocked from its first row.
                $rows = $color !== null ? collect([['size' => null] + ($rows->first() ?? [])]) : collect();
            }

            return ['color' => $color, 'rows' => $rows->values()];
        })->filter(fn ($group) => $group['rows']->isNotEmpty())->values();

        // The older comma-separated Colors field: one option per color at the product's price and stock.
        if ($groups->isEmpty() && $request->filled('colors')) {
            $groups = collect($this->colorList($request))->map(fn ($color) => [
                'color' => $color, 'rows' => collect([['size' => null, 'stock' => $request->input('stock')]]),
            ]);
        }
        if ($groups->isEmpty()) {
            return [];
        }

        if ($groups->count() > 1 && $groups->contains('color', null)) {
            throw ValidationException::withMessages(['options' => 'Name every color, so buyers can tell them apart.']);
        }
        if ($groups->pluck('color')->filter()->map(fn ($color) => Str::lower($color))->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['options' => 'Each color can only be added once. Put all of its sizes in one card.']);
        }
        $fallbackPrice = $request->input('price');
        $rows = [];
        foreach ($groups as $group) {
            $label = $group['color'] ?? 'this product';
            if ($group['rows']->pluck('size')->filter()->map(fn ($size) => Str::lower($size))->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['options' => "Each size can only be listed once for {$label}."]);
            }
            foreach ($group['rows'] as $row) {
                $price = filled($row['price'] ?? null) ? $row['price'] : $fallbackPrice;
                if (! is_numeric($price)) {
                    throw ValidationException::withMessages(['options' => 'Enter a price for '.($row['size'] ? "{$label} size {$row['size']}" : $label).'.']);
                }
                $rows[] = ['color' => $group['color'], 'size' => $row['size'], 'price' => round((float) $price, 2), 'stock' => (int) ($row['stock'] ?? 0)];
            }
        }

        return $rows;
    }

    /**
     * Turns a new product's colors and sizes into options; the default option it was created with becomes the first one.
     */
    private function createProductOptions(Product $product, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $legacy = Product::usesLegacySchema();
        $default = $legacy ? null : $product->variants()->oldest('id')->first();
        foreach ($rows as $index => $row) {
            $columns = ['name' => ProductVariant::nameFor($row['color'], $row['size']), 'stock' => $row['stock']] + ($legacy
                ? ['color' => $row['color'], 'size' => $row['size'], 'price' => $row['price']]
                : ['options' => array_filter(['color' => $row['color'], 'size' => $row['size']], fn ($value) => filled($value)), 'price_minor' => (int) round($row['price'] * 100)]);
            if ($index === 0 && $default) {
                $default->update($columns);
                continue;
            }
            $variant = new ProductVariant(['product_id' => $product->id, 'is_active' => true,
                'sku' => $this->variantSku($product->sku, implode('-', array_filter([$row['color'], $row['size']])))]);
            $variant->fill($columns + ($legacy ? [] : ['low_stock_threshold' => Product::lowStockThreshold(), 'weight_grams' => $default?->weight_grams]));
            $variant->save();
        }
        Product::syncStockFromVariants($product->id);
    }

    /**
     * Colors typed in the older comma-separated field ("Black, White, Olive"): trimmed, de-duplicated ignoring case.
     */
    private function colorList(Request $request): array
    {
        $request->validate(['colors' => 'nullable|string|max:600']);
        $colors = collect(explode(',', (string) $request->input('colors')))
            ->map(fn ($color) => Str::squish($color))->filter()
            ->unique(fn ($color) => Str::lower($color))->values();

        if ($colors->contains(fn ($color) => mb_strlen($color) > 40)) {
            throw ValidationException::withMessages(['colors' => 'Keep each color name to 40 characters or fewer.']);
        }
        if ($colors->count() > 20) {
            throw ValidationException::withMessages(['colors' => 'Add up to 20 colors. You can add more after saving.']);
        }

        return $colors->all();
    }

    private function variantSku(?string $base, string $size): string
    {
        $sku = Str::upper(Str::limit(($base ?: 'ZAY-'.Str::random(6)).'-'.Str::slug($size, ''), 70, ''));

        return ProductVariant::where('sku', $sku)->exists() ? 'ZAY-'.Str::upper(Str::random(8)) : $sku;
    }

    public function handover(Request $request)
    {
        $request->merge(['status' => 'shipping']);

        return $this->orders($request);
    }

    public function reports(Request $request)
    {
        $input = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $today = CarbonImmutable::now(self::REPORT_TIMEZONE);
        $to = isset($input['to']) ? CarbonImmutable::createFromFormat('Y-m-d', $input['to'], self::REPORT_TIMEZONE)->endOfDay() : $today->endOfDay();
        $from = isset($input['from']) ? CarbonImmutable::createFromFormat('Y-m-d', $input['from'], self::REPORT_TIMEZONE)->startOfDay() : $to->subDays(29)->startOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'The start date must be on or before the end date.']);
        }
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => 'Choose a report range of one year or less.']);
        }

        $report = Order::usesLegacySchema()
            ? $this->legacyReport($this->shopId(), $from, $to)
            : $this->sellerOrderReport($this->shopId(), $from, $to);

        return view('seller.reports', $report + [
            'from' => $from,
            'to' => $to,
            'seriesMonthly' => $this->seriesIsMonthly($from, $to),
            'commissionPercent' => (int) round(self::COMMISSION_RATE * 100),
        ]);
    }

    public function account()
    {
        $user = auth()->user();
        $legacy = Product::usesLegacySchema();
        $shop = $legacy ? $user->sellerProfile : $user->sellerShop();
        $addresses = $user->addresses();

        return view('seller.account', [
            'user' => $user,
            'storeName' => $this->storeName(),
            'storeDescription' => $legacy ? null : $shop?->description,
            'supportsStoreDescription' => ! $legacy,
            'shopStatus' => $shop?->status,
            'defaultAddress' => (clone $addresses)->orderByDesc('is_default')->oldest('id')->first(),
            'addressCount' => (clone $addresses)->count(),
        ]);
    }

    public function updateStore(Request $request, SellerApplicationService $applications)
    {
        $user = $request->user();
        $data = $request->validate([
            // Same rule as the shop name on the seller application.
            'store_name' => ['required', 'string', 'min:3', 'max:30', function (string $attribute, mixed $value, \Closure $fail) use ($applications, $user) {
                if (is_string($value) && $applications->shopNameTaken($value, $user->id)) {
                    $fail('This shop name is already taken.');
                }
            }],
            'store_description' => 'nullable|string|max:1000',
        ], ['store_name.min' => 'The shop name must be 3 to 30 characters.', 'store_name.max' => 'The shop name must be 3 to 30 characters.']);
        $data['store_name'] = trim($data['store_name']);
        if ($applications->available()) {
            $user->sellerApplication?->update(['shop_name' => $data['store_name']]);
        }

        if (Product::usesLegacySchema()) {
            $profile = $user->sellerProfile ?? new SellerProfile(['user_id' => $user->id]);
            $profile->store_name = $data['store_name'];
            $profile->save();
        } else {
            $user->sellerShop()->update([
                'name' => $data['store_name'],
                'description' => $data['store_description'] ?? null,
            ]);
        }

        return back()->with('status', 'Store details updated.');
    }

    /**
     * The id stored in products.seller_id, which checkout also copies into orders.seller_id.
     */
    private function shopId(): int
    {
        return Product::usesLegacySchema() ? (int) auth()->id() : (int) auth()->user()->sellerShop()->id;
    }

    private function storeName(): string
    {
        return auth()->user()->sellerStoreName();
    }

    private function productFilters(Request $request, bool $withStock): array
    {
        $rules = [
            'q' => 'nullable|string|max:100',
            'category' => ['nullable', Rule::in(array_keys(config('marketplace.categories')))],
        ];
        if ($withStock) {
            $rules['stock'] = ['nullable', Rule::in(['all', 'in', 'low', 'out'])];
        }
        $filters = $request->validate($rules) + ['q' => null, 'category' => null, 'stock' => null];
        $filters['stock'] = $withStock ? ($filters['stock'] ?? 'all') : 'all';

        return $filters;
    }

    private function filteredProducts(array $filters): Builder
    {
        $legacy = Product::usesLegacySchema();

        return $this->withStockDetails(Product::where('seller_id', $this->shopId()))
            ->when(filled($filters['q']), function (Builder $query) use ($filters, $legacy) {
                $term = '%'.addcslashes($filters['q'], '%_\\').'%';
                $query->where(fn (Builder $nested) => $nested->where('name', 'like', $term)
                    ->when($legacy, fn (Builder $q) => $q->orWhere('sku', 'like', $term))
                    ->when(! $legacy, fn (Builder $q) => $q->orWhereHas('variants', fn (Builder $variant) => $variant->where('sku', 'like', $term))));
            })
            ->when(filled($filters['category']), fn (Builder $query) => $query->inCategories([$filters['category']]))
            ->when($filters['stock'] !== 'all', fn (Builder $query) => $this->whereStock($query, $filters['stock']));
    }

    private function stockStatesFor($products): Collection
    {
        return $products->getCollection()->mapWithKeys(fn (Product $product) => [$product->id => $this->stockState($product)]);
    }

    private function withStockDetails(Builder $query): Builder
    {
        return Product::usesLegacySchema()
            ? $query->with(['additionalCategories', 'variants' => fn ($variants) => $this->forSale($variants)->orderBy('id')])
            : $query->with(['category', 'additionalCategories', 'images', 'defaultVariant', 'variants' => fn ($variants) => $this->forSale($variants)->orderBy('id')]);
    }

    /**
     * Options the seller still sells. Removed sizes that past orders point at are kept inactive; on the newer schema
     * the first option also goes inactive when the whole product is hidden, so it always counts.
     */
    private function forSale($variants)
    {
        return Product::usesLegacySchema()
            ? $variants->where('is_active', true)
            : $variants->where(fn ($query) => $query->where('is_active', true)
                ->orWhereRaw('product_variants.id = (SELECT MIN(pv.id) FROM product_variants pv WHERE pv.product_id = product_variants.product_id)'));
    }

    /**
     * Stock states: "out" has nothing to sell, "low" has some stock at or under the fixed alert level, "in" is healthy.
     */
    private function whereStock(Builder $query, string $state): Builder
    {
        $threshold = Product::lowStockThreshold();
        if (Product::usesLegacySchema()) {
            return match ($state) {
                'out' => $query->where('stock', '<=', 0),
                'low' => $query->where('stock', '>', 0)->where('stock', '<=', $threshold),
                default => $query->where('stock', '>', $threshold),
            };
        }

        $low = fn (Builder $variant) => $variant->where('stock', '>', 0)->where('stock', '<=', $threshold);

        return match ($state) {
            'out' => $query->whereDoesntHave('variants', fn (Builder $variant) => $variant->where('stock', '>', 0)),
            'low' => $query->whereHas('variants', $low),
            default => $query->whereHas('variants', fn (Builder $variant) => $variant->where('stock', '>', 0))->whereDoesntHave('variants', $low),
        };
    }

    private function stockState(Product $product): array
    {
        if (Product::usesLegacySchema() && $product->variants->isEmpty()) {
            $total = (int) $product->stock;
            $low = $total > 0 && $total <= $product->low_stock_threshold;
        } else {
            $total = (int) $product->variants->sum('stock');
            $low = $product->variants->contains(fn (ProductVariant $variant) => $variant->stock > 0
                && $variant->stock <= $product->low_stock_threshold);
        }

        return ['total' => $total, 'state' => $total <= 0 ? 'out' : ($low ? 'low' : 'in')];
    }

    private function orderSummary(int $shopId): array
    {
        $legacy = Order::usesLegacySchema();
        $groups = $legacy ? self::ORDER_STATUS_GROUPS : self::SELLER_ORDER_STATUS_GROUPS;
        $orders = $legacy ? Order::where('seller_id', $shopId) : SellerOrder::where('seller_id', $shopId);
        $statusCounts = (clone $orders)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = ['all' => (int) $statusCounts->sum()];
        foreach ($groups as $group => $statuses) {
            $counts[$group] = collect($statuses)->sum(fn ($status) => (int) $statusCounts->get($status, 0));
        }

        $completed = (clone $orders)->whereIn('status', $groups['delivered']);
        $monthStart = now(self::REPORT_TIMEZONE)->startOfMonth()->utc();

        if ($legacy) {
            $revenue = (float) (clone $completed)->sum('subtotal');
            $monthRevenue = (float) (clone $completed)->where('completed_at', '>=', $monthStart)->sum('subtotal');
            $recent = (clone $orders)->with('buyer')->latest('placed_at')->limit(5)->get()
                ->map(fn (Order $order) => [
                    'reference' => $order->order_number,
                    'buyer' => $order->buyer?->name ?? $order->recipient_name ?? 'Buyer',
                    'date' => $order->placed_at ?? $order->created_at,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                ]);
        } else {
            $revenue = (clone $completed)->sum('subtotal_minor') / 100;
            $monthRevenue = (clone $completed)->where('updated_at', '>=', $monthStart)->sum('subtotal_minor') / 100;
            $recent = (clone $orders)->with('order.buyer')->latest()->limit(5)->get()
                ->map(fn (SellerOrder $order) => [
                    'reference' => $order->order?->reference ?? '#'.$order->id,
                    'buyer' => $order->order?->buyer?->name ?? 'Buyer',
                    'date' => $order->created_at,
                    'status' => $order->status,
                    'total' => ($order->subtotal_minor + $order->shipping_fee_minor) / 100,
                ]);
        }

        return compact('counts', 'revenue', 'monthRevenue', 'recent');
    }

    private function ratingSummary(int $shopId): array
    {
        if (! Schema::hasTable('product_reviews')) {
            return ['average' => null, 'count' => 0];
        }

        $reviews = ProductReview::whereIn('product_id', Product::where('seller_id', $shopId)->select('id'));
        $count = (clone $reviews)->count();

        return ['average' => $count ? round((float) (clone $reviews)->avg('rating'), 1) : null, 'count' => $count];
    }

    private function legacyReport(int $shopId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = Order::where('seller_id', $shopId)->whereBetween('placed_at', [$from->utc(), $to->utc()]);
        $completedQuery = (clone $orders)->where('status', 'completed');
        $completed = (clone $completedQuery)->get(['id', 'subtotal', 'placed_at']);
        $sales = (float) $completed->sum(fn (Order $order) => (float) $order->subtotal);
        $openValue = (float) (clone $orders)->whereNotIn('status', ['completed', 'cancelled'])->sum('subtotal');

        $productTotals = OrderItem::whereIn('order_id', (clone $completedQuery)->select('id'))
            ->selectRaw('product_id, MAX(product_name) as product_name, SUM(quantity) as units, SUM(line_total) as revenue')
            ->groupBy('product_id')
            ->get();
        $catalog = $this->withStockDetails(Product::whereIn('id', $productTotals->pluck('product_id')))->get()->keyBy('id');
        $productRows = $productTotals->map(fn ($row) => [
            'name' => $catalog->get($row->product_id)?->name ?? Str::before((string) $row->product_name, ' — '),
            'category' => $catalog->get($row->product_id)?->category_label ?? 'Uncategorized',
            'image' => $catalog->get($row->product_id)?->image_url,
            'units' => (int) $row->units,
            'revenue' => (float) $row->revenue,
        ]);

        return [
            'supportsBreakdown' => true,
            'stats' => [
                'sales' => $sales,
                'delivered' => $completed->count(),
                'placed' => (clone $orders)->where('status', '!=', 'cancelled')->count(),
                'cancelled' => (clone $orders)->where('status', 'cancelled')->count(),
                'average' => $completed->count() ? $sales / $completed->count() : 0,
                'commission' => round($sales * self::COMMISSION_RATE, 2),
                'openValue' => $openValue,
            ],
            'series' => $this->salesSeries($completed, $from, $to),
            'categories' => $productRows->groupBy('category')
                ->map(fn (Collection $rows, $label) => ['label' => $label, 'amount' => (float) $rows->sum('revenue')])
                ->sortByDesc('amount')->values(),
            'topProducts' => $productRows->sortByDesc('revenue')->take(10)->values(),
        ];
    }

    private function sellerOrderReport(int $shopId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = SellerOrder::where('seller_id', $shopId)->whereBetween('created_at', [$from->utc(), $to->utc()]);
        $completed = (clone $orders)->whereIn('status', self::SELLER_ORDER_STATUS_GROUPS['delivered']);
        $sales = (clone $completed)->sum('subtotal_minor') / 100;
        $deliveredCount = (clone $completed)->count();

        return [
            'supportsBreakdown' => false,
            'stats' => [
                'sales' => $sales,
                'delivered' => $deliveredCount,
                'placed' => (clone $orders)->where('status', '!=', 'cancelled')->count(),
                'cancelled' => (clone $orders)->where('status', 'cancelled')->count(),
                'average' => $deliveredCount ? $sales / $deliveredCount : 0,
                'commission' => round($sales * self::COMMISSION_RATE, 2),
                'openValue' => (clone $orders)->whereNotIn('status', ['delivered', 'completed', 'cancelled'])->sum('subtotal_minor') / 100,
            ],
            'series' => collect(),
            'categories' => collect(),
            'topProducts' => collect(),
        ];
    }

    /**
     * Delivered sales grouped by order date: daily for ranges up to two months, monthly beyond that.
     */
    private function salesSeries(Collection $completed, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $monthly = $this->seriesIsMonthly($from, $to);
        $format = $monthly ? 'Y-m' : 'Y-m-d';
        $totals = $completed->groupBy(fn (Order $order) => $order->placed_at->copy()->timezone(self::REPORT_TIMEZONE)->format($format))
            ->map(fn (Collection $orders) => (float) $orders->sum(fn (Order $order) => (float) $order->subtotal));

        $series = collect();
        for ($cursor = $monthly ? $from->startOfMonth() : $from; $cursor->lte($to); $cursor = $monthly ? $cursor->addMonth() : $cursor->addDay()) {
            $key = $cursor->format($format);
            $series->push([
                'label' => $monthly ? $cursor->format('M Y') : $cursor->format('M j'),
                'amount' => (float) $totals->get($key, 0),
            ]);
        }

        return $series;
    }

    private function seriesIsMonthly(CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $from->diffInDays($to) > 62;
    }

    private function validatedProduct(Request $request, ?Product $product = null): array
    {
        $variantId = Product::usesLegacySchema() ? 'NULL' : ($product?->defaultVariant?->id ?? 'NULL');
        // Older forms sent a main category plus extras; treat them as one list of ticked categories.
        if (! $request->has('categories') && $request->filled('category')) {
            $request->merge(['categories' => array_values(array_unique([$request->input('category'), ...(array) $request->input('additional_categories', [])]))]);
        }
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'sku' => Product::usesLegacySchema()
                ? 'nullable|string|max:80|unique:products,sku,'.($product?->id ?? 'NULL')
                : "nullable|string|max:80|unique:product_variants,sku,{$variantId}",
            'categories' => 'required|array|min:1|max:6',
            'categories.*' => ['distinct', Rule::in(array_keys(config('marketplace.categories')))],
            'gender' => 'nullable|in:men,women,unisex',
            'description' => 'nullable|string|max:2000',
            'price' => ($product ? 'sometimes|' : '').'required|numeric|min:0|max:99999999.99',
            'original_price' => 'nullable|numeric|gte:price|max:99999999.99',
            'stock' => ($product ? 'sometimes|' : '').'required|integer|min:0|max:1000000',
            'weight_grams' => 'nullable|integer|min:0|max:1000000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photos' => 'nullable|array|max:'.self::PHOTOS_PER_UPLOAD,
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_photos' => 'nullable|array',
            'remove_photos.*' => 'integer',
            'image_url' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! filter_var($value, FILTER_VALIDATE_URL) && ! Str::startsWith($value, ['/images/', '/storage/'])) {
                        $fail('The image must be a valid URL or a local product image path.');
                    }
                },
            ],
            'badge' => 'nullable|in:New,Sale,Best Seller',
        ], [
            'categories.required' => 'Choose at least one category.',
            'categories.min' => 'Choose at least one category.',
            'categories.max' => 'Choose up to 6 categories.',
            'categories.*.in' => 'Choose categories from the list.',
            'photos.max' => 'Upload up to '.self::PHOTOS_PER_UPLOAD.' photos at a time.',
            'photos.*.image' => 'Each photo must be a JPG, PNG, or WebP image.',
            'photos.*.mimes' => 'Each photo must be a JPG, PNG, or WebP image.',
            'photos.*.max' => 'Each photo must be 5 MB or smaller.',
        ]);
        unset($data['photos']);
        [$data['category'], $data['additional_categories']] = $this->splitCategories(array_values(array_unique($data['categories'])), $product);
        unset($data['categories']);

        $kept = $product ? $product->extra_photos->whereNotIn('id', array_map('intval', $data['remove_photos'] ?? []))->count() : 0;
        if ($kept + count($request->file('photos', [])) > self::MAX_EXTRA_PHOTOS) {
            throw ValidationException::withMessages(['photos' => 'A product can have up to '.(self::MAX_EXTRA_PHOTOS + 1).' photos: the main photo and '.self::MAX_EXTRA_PHOTOS.' more.']);
        }

        return $data;
    }

    /**
     * Picks the main category from the ticked ones: the product's current one if still ticked, else one matching the
     * seller's registered shop category (so compliance checks stay meaningful), else the first ticked. The rest are extras.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function splitCategories(array $categories, ?Product $product): array
    {
        $shopCategory = auth()->user()?->sellerApplication?->shop_category;
        $shopGroup = $shopCategory ? (config('marketplace.category_groups')[$shopCategory] ?? [$shopCategory]) : [];
        $main = in_array($product?->category_key, $categories, true)
            ? $product->category_key
            : (collect($categories)->first(fn ($category) => in_array($category, $shopGroup, true)) ?? $categories[0]);

        return [$main, array_values(array_diff($categories, [$main]))];
    }

    /** Removes the ticked gallery photos and appends newly uploaded ones after the existing photos. */
    private function saveExtraPhotos(Request $request, Product $product, array $removeIds = []): void
    {
        $product->load('images');
        foreach ($product->extra_photos->whereIn('id', $removeIds) as $image) {
            $this->deleteStoredProductPhoto($image->path);
            $image->delete();
        }

        $order = (int) $product->images()->max(Product::usesLegacySchema() ? 'sort_order' : 'position');
        foreach ($request->file('photos', []) as $file) {
            $product->images()->create([
                'path' => Storage::url($file->store('products/'.auth()->id(), 'public')),
                'alt_text' => $product->name,
                'sort_order' => ++$order,
            ]);
        }
        $product->unsetRelation('images');
        // Without a main photo the first gallery photo stands in (the normalized schema already reads it that way).
        if (Product::usesLegacySchema() && blank($product->image_url) && ($first = $product->images()->first())) {
            $product->update(['image_url' => $first->path]);
        }
    }

    /** Deletes an uploaded photo file; shared catalog images under /images are left alone. */
    private function deleteStoredProductPhoto(?string $path): void
    {
        $relative = Str::startsWith((string) $path, '/storage/') ? Str::after($path, '/storage/') : null;
        if ($relative && Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }

    private function storeProductImage(Request $request, array $data, ?Product $product = null): array
    {
        unset($data['image']);

        if (! $request->hasFile('image')) {
            return $data;
        }

        $path = $request->file('image')->store('products/'.auth()->id(), 'public');
        $oldPath = $product && Str::startsWith((string) $product->image_url, '/storage/')
            ? Str::after($product->image_url, '/storage/')
            : null;

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $data['image_url'] = Storage::url($path);

        return $data;
    }
}
