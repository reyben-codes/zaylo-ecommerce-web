<?php

namespace App\Support;

use App\Services\CheckoutService;
use App\Models\Address;
use App\Models\Announcement;
use App\Models\CartItem;
use App\Models\IdentityVerification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderReceipts;
use App\Services\ParcelTracking;
use App\Services\ProductReviews;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * JSON shapes returned by the mobile API (routes/api.php). Keep the keys stable: the Flutter models in the
 * mobile app parse exactly these.
 */
class MobilePayload
{
    public const STATUS_GROUPS = [
        'to_ship' => ['placed', 'confirmed', 'processing', 'ready_for_pickup', 'assigned'],
        'shipped' => ['picked_up', 'in_transit', 'out_for_delivery'],
        'delivered' => ['delivered', 'completed'],
        'cancelled' => ['cancelled'],
    ];

    public const STATUS_MESSAGES = [
        'placed' => 'Order placed. Waiting for the seller to confirm.',
        'confirmed' => 'The seller has confirmed your order.',
        'processing' => 'The seller is preparing your items.',
        'ready_for_pickup' => 'Your order is ready for courier pickup.',
        'assigned' => 'A courier has been assigned to your order.',
        'picked_up' => 'The courier has picked up your order.',
        'in_transit' => 'Your order is on its way.',
        'out_for_delivery' => 'Your order is out for delivery today.',
        'delivered' => 'The rider delivered your order. Tap "Order received" once you have it.',
        'completed' => 'Your order is complete.',
        'cancelled' => 'This order was cancelled.',
    ];

    /** @var array<int, ?string> shop names already looked up in this request */
    private static array $shopNames = [];

    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => User::portalOf($user->role),
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'email_verified' => $user->hasVerifiedEmail(),
            'identity_verified' => $user->isIdentityVerified(),
            'has_password' => filled($user->getAuthPassword()),
            'google_linked' => filled($user->google_id ?? null),
            'avatar_url' => $user->avatar_url,
        ];
    }

    /** The buyer's ID verification: whether they can check out, and their latest upload. */
    public static function identity(User $user): array
    {
        $enabled = Schema::hasTable('identity_verifications');
        $submission = $enabled ? $user->latestIdentityVerification()->first() : null;

        return [
            'enabled' => $enabled,
            'verified' => $user->isIdentityVerified(),
            'submission' => $submission ? [
                'status' => $submission->status,
                'id_type' => $submission->id_type,
                'id_type_label' => $submission->id_type_label,
                'rejection_reason' => $submission->rejection_reason,
                'submitted_at' => $submission->updated_at?->toIso8601String(),
            ] : null,
            'id_types' => collect(IdentityVerification::ID_TYPES)->map(fn ($label, $key) => ['id' => $key, 'label' => $label])->values(),
        ];
    }

    public static function product(Product $product, bool $detailed = false, bool $saved = false): array
    {
        $data = [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'category' => $product->category_key,
            'category_label' => $product->category_label,
            'price' => (float) $product->price,
            'original_price' => $product->original_price === null ? null : (float) $product->original_price,
            'badge' => $product->badge,
            // Marked 18+ by its seller: ordering it needs an approved ID.
            'adult_only' => $product->is_adult_only,
            'image_url' => self::imageUrl($product->image_url ?: $product->images->first()?->path),
            'has_options' => (int) ($product->active_variants_count ?? 0) > 1,
            'saved' => $saved,
            // How many can be bought across its active options, shown on every product card.
            'stock' => $product->available_stock,
        ];

        // Present when the query used Product::withReviewSummary() or loaded the counts.
        if (array_key_exists('reviews_count', $product->getAttributes())) {
            $data['review_count'] = (int) $product->reviews_count;
            $data['rating'] = $product->reviews_count > 0 ? round((float) $product->reviews_avg_rating, 1) : null;
        }

        // Present on a shop's listing (App\Support\Shop::listing).
        if (array_key_exists('units_sold', $product->getAttributes())) {
            $data['units_sold'] = (int) $product->units_sold;
        }

        if ($detailed) {
            $data += [
                'description' => $product->description,
                'seller_name' => self::shopName($product->seller_id),
                'shop_id' => $product->seller_id,
                'images' => collect([$product->image_url])
                    ->concat($product->images->pluck('path'))
                    ->filter()->unique()->map(fn ($path) => self::imageUrl($path))->values(),
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price' => (float) $product->priceFor($variant),
                    'stock' => (int) $variant->stock,
                ])->values(),
            ];
        }

        return array_filter($data, fn ($value) => $value !== null);
    }

    /** A shop's page header and its row in search results and the followed list. */
    public static function shop(Shop $shop, ?User $viewer = null): array
    {
        $rating = $shop->rating();

        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'description' => $shop->description,
            'location' => $shop->location,
            'avatar_url' => self::imageUrl($shop->owner->avatar_url),
            'cover_url' => self::imageUrl($shop->owner->shop_cover_url),
            'product_count' => $shop->products()->count(),
            'followers' => $shop->followerCount(),
            'rating' => $rating['average'],
            'review_count' => $rating['count'],
            'sold' => $shop->soldCount(),
            'following' => $shop->isFollowedBy($viewer),
        ];
    }

    public static function cartItem(CartItem $item): array
    {
        $product = $item->product;

        return [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unitPrice(),
            'line_total' => $item->unitPrice() * $item->quantity,
            'available_stock' => $item->availableStock(),
            'variant_name' => $item->variant?->name,
            'seller_id' => $product?->seller_id,
            'seller_name' => self::shopName($product?->seller_id),
            'product' => $product ? self::product($product) : null,
        ];
    }

    public static function order(Order $order, bool $detailed = false): array
    {
        $data = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => Str::headline($order->status),
            'status_message' => self::STATUS_MESSAGES[$order->status] ?? 'Your order status was updated.',
            'can_cancel' => in_array($order->status, CheckoutService::BUYER_CANCELLABLE, true),
            // Shown as a disabled Cancel button with this reason once the seller confirmed the order.
            'cancel_locked_reason' => self::cancelLockedReason($order),
            'cancel_reason' => BuyerOrderTabs::reasonLabel(Schema::hasColumn('orders', 'cancel_reason') ? $order->cancel_reason : null),
            'cancelled_at' => $order->status === 'cancelled' ? BuyerOrderTabs::cancelledAt($order)?->toIso8601String() : null,
            'can_confirm_receipt' => $order->status === 'delivered',
            'can_buy_again' => in_array($order->status, ['cancelled', 'delivered', 'completed'], true),
            // GCash paid to the seller: the reference and the seller's check (null for cash on delivery).
            'gcash' => \App\Services\GcashPayments::forBuyer($order),
            'can_review' => self::canReview($order),
            // The list shows Return / Refund on the card too, when the complaints are loaded.
            'can_return' => in_array($order->status, ['delivered', 'completed'], true) && $order->isWithinReturnWindow() && $order->relationLoaded('disputes') && $order->disputes->isEmpty(),
            'subtotal' => (float) $order->subtotal,
            'shipping_fee' => (float) $order->shipping_fee,
            'shipping_discount' => (float) $order->shipping_discount,
            'total' => (float) $order->total,
            'payment_method' => $order->payment_method,
            'courier' => $order->courierName(),
            'voucher_code' => $order->voucher_code,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'item_count' => (int) $order->items->sum('quantity'),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_slug' => $item->product?->slug,
                'product_name' => $item->product_name,
                'image_url' => $item->product ? self::imageUrl($item->product->image_url ?: $item->product->images->first()?->path) : null,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'line_total' => (float) $item->line_total,
            // The list only needs whether an item waits for a rating ("To review"); the detail adds the full state.
            ] + ($detailed ? self::reviewState($order, $item) : ['can_review' => in_array($order->status, ['delivered', 'completed'], true)
                && $item->product_id && $item->relationLoaded('review') && ! $item->review]))->values(),
        ];

        if ($detailed) {
            $data += [
                'recipient_name' => $order->recipient_name,
                'phone' => $order->phone,
                'shipping_address' => is_array($order->shipping_address)
                    ? collect($order->shipping_address)->filter()->join(', ')
                    : $order->shipping_address,
                'notes' => $order->notes,
                'payment_status' => $order->payment?->status,
                'tracking_number' => ($shipment = $order->deliveryShipment())?->tracking_number ?? $shipment?->tracking_code,
                // Completes on its own at this time unless the buyer confirms first (App\Services\OrderReceipts).
                'auto_confirm_at' => OrderReceipts::autoConfirmAt($order)?->toIso8601String(),
                'delivery_photo_url' => $shipment?->delivery_proof_path ? route('mobile.orders.delivery-photo', $order) : null,
                'rider_chat' => self::riderChat($order),
                // Where the buyer pinned the delivery, and where the rider is while it is out for delivery.
                'tracking' => app(ParcelTracking::class)->forBuyer($order),
                'complaint' => self::complaint($order),
                'delivered_at' => $order->deliveredAt()?->toIso8601String(),
                'return_deadline' => $order->returnDeadline()?->toIso8601String(),
                'is_within_return_window' => $order->isWithinReturnWindow(),
                'is_return_window_expired' => $order->isReturnWindowExpired(),
                'can_complain' => $order->relationLoaded('disputes') && $order->status !== 'cancelled' && $order->disputes->isEmpty(),
                'history' => ($order->relationLoaded('statusHistory') ? $order->statusHistory : collect())->map(fn ($entry) => [
                    'status' => $entry->status,
                    'label' => Str::headline($entry->status),
                    'note' => $entry->note,
                    'at' => $entry->created_at?->toIso8601String(),
                ])->values(),
            ];
        }

        return $data;
    }

    /** Why Cancel is disabled: the seller confirmed the order and is preparing it. Null when Cancel is not shown at all. */
    public static function cancelLockedReason(Order $order): ?string
    {
        $preparing = array_diff(self::STATUS_GROUPS['to_ship'], CheckoutService::BUYER_CANCELLABLE);

        return in_array($order->status, $preparing, true)
            ? 'The seller already confirmed this order and is preparing it, so it can no longer be cancelled. Message the seller if something is wrong.'
            : null;
    }

    /** A delivered order with an item the buyer has not rated yet ("To review"). */
    public static function canReview(Order $order): bool
    {
        return in_array($order->status, ['delivered', 'completed'], true)
            && $order->relationLoaded('items')
            && $order->items->contains(fn (OrderItem $item) => $item->product_id && $item->relationLoaded('review') && ! $item->review);
    }

    /** Whether the buyer can rate a delivered item, or the rating they gave. */
    private static function reviewState(Order $order, OrderItem $item): array
    {
        if (Order::usesLegacySchema()) {
            $item->setRelation('order', $order);
        }

        return [
            'can_review' => app(ProductReviews::class)->canReview($item),
            'review_rating' => $item->review?->rating,
        ];
    }

    /** The complaint filed about the order, if any. */
    private static function complaint(Order $order): ?array
    {
        $dispute = $order->relationLoaded('disputes') ? $order->disputes->first() : null;

        return $dispute ? [
            'id' => $dispute->id,
            'status' => $dispute->status,
            'status_label' => $dispute->status_label,
        ] : null;
    }

    /** The buyer's chat with the rider, once one is assigned to the delivery. */
    private static function riderChat(Order $order): ?array
    {
        $shipment = $order->deliveryShipment();
        $riderUserId = $shipment?->riderUserId();
        if ($riderUserId === null) {
            return null;
        }

        return [
            'rider_name' => Str::of(User::find($riderUserId)?->name ?? 'Your rider')->before(' ')->toString(),
            'open' => $shipment->chatIsOpen(),
            'unread' => $shipment->unreadMessagesFor(rider: false),
        ];
    }

    /**
     * Live admin announcements. The key changes when the admin edits one, so a notice the user dismissed shows again
     * after an update, as on the website.
     *
     * @param  iterable<Announcement>  $announcements
     */
    public static function announcements(iterable $announcements): array
    {
        return collect($announcements)->map(fn (Announcement $notice) => [
            'key' => $notice->id.'-'.$notice->updated_at?->timestamp,
            'title' => $notice->title,
            'body' => $notice->body,
        ])->values()->all();
    }

    public static function address(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'barangay' => $address->barangay,
            'barangay_code' => $address->barangay_code,
            'city' => $address->city,
            'city_municipality_code' => $address->city_municipality_code,
            'province' => $address->province,
            'province_code' => $address->province_code,
            'region_name' => $address->region_name,
            'region_code' => $address->region_code,
            'postal_code' => $address->postal_code,
            'is_default' => (bool) $address->is_default,
            'is_structured' => $address->isStructured(),
            'formatted' => $address->formatted(),
            'pin' => ParcelTracking::addressPin($address),
        ];
    }

    public static function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // Uploaded photos go straight to where the file lives (Cloudflare R2), skipping the /storage redirect.
        return Str::startsWith($path, '/storage/') ? Storage::disk('public')->url(Str::after($path, '/storage/')) : url($path);
    }

    /** The shop name buyers see for a product's seller_id (see App\Support\Shop). */
    public static function shopName(?int $sellerId): ?string
    {
        if (! $sellerId) {
            return null;
        }

        return self::$shopNames[$sellerId] ??= Shop::find($sellerId)?->name;
    }
}
