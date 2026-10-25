<?php

namespace App\Support;

use App\Models\Address;
use App\Models\Announcement;
use App\Models\CartItem;
use App\Models\IdentityVerification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductReviews;
use Illuminate\Support\Facades\Schema;
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
        'delivered' => ['completed'],
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
        'completed' => 'Your order has been delivered.',
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
            'image_url' => self::imageUrl($product->image_url ?: $product->images->first()?->path),
            'has_options' => (int) ($product->active_variants_count ?? 0) > 1,
            'saved' => $saved,
        ];

        // Present when the query used Product::withReviewSummary() or loaded the counts.
        if (array_key_exists('reviews_count', $product->getAttributes())) {
            $data['review_count'] = (int) $product->reviews_count;
            $data['rating'] = $product->reviews_count > 0 ? round((float) $product->reviews_avg_rating, 1) : null;
        }

        if ($detailed) {
            $data += [
                'description' => $product->description,
                'stock' => (int) $product->stock,
                'seller_name' => self::shopName($product->seller_id),
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
            'can_cancel' => in_array($order->status, ['placed', 'confirmed'], true),
            'subtotal' => (float) $order->subtotal,
            'shipping_fee' => (float) $order->shipping_fee,
            'shipping_discount' => (float) $order->shipping_discount,
            'total' => (float) $order->total,
            'payment_method' => $order->payment_method,
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
            ] + ($detailed ? self::reviewState($order, $item) : []))->values(),
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
                'rider_chat' => self::riderChat($order),
                'complaint' => self::complaint($order),
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
        ];
    }

    public static function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://']) ? $path : url($path);
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
