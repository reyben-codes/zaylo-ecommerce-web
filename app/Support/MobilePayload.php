<?php

namespace App\Support;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * JSON shapes returned by the mobile API. Keep these stable: the Flutter
 * models in mobile_app/lib/models parse exactly these keys.
 */
class MobilePayload
{
    public const STATUS_GROUPS = [
        'to_ship' => ['placed', 'confirmed', 'processing', 'ready_for_pickup', 'assigned'],
        'shipped' => ['picked_up', 'in_transit'],
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
        'completed' => 'Your order has been delivered.',
        'cancelled' => 'This order was cancelled.',
    ];

    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'email_verified' => $user->hasVerifiedEmail(),
            'has_password' => filled($user->getAuthPassword()),
            'google_linked' => filled($user->google_id ?? null),
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
            'image_url' => self::imageUrl($product->image_url ?: optional($product->images->first())->path),
            'has_options' => (int) ($product->active_variants_count ?? 0) > 1,
            'saved' => $saved,
        ];

        if ($detailed) {
            $data += [
                'description' => $product->description,
                'stock' => $product->stock,
                'seller_name' => $product->relationLoaded('seller') ? self::sellerName($product) : null,
                'images' => collect([$product->image_url])
                    ->concat($product->images->pluck('path'))
                    ->filter()->unique()->map(fn ($path) => self::imageUrl($path))->values(),
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price' => (float) $variant->price,
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
            'seller_name' => $product && $product->relationLoaded('seller') ? self::sellerName($product) : null,
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
                'image_url' => $item->product ? self::imageUrl($item->product->image_url ?: optional($item->product->images->first())->path) : null,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'line_total' => (float) $item->line_total,
            ])->values(),
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
                'tracking_number' => $order->shipment?->tracking_number,
                'history' => $order->statusHistory->map(fn ($entry) => [
                    'status' => $entry->status,
                    'label' => Str::headline($entry->status),
                    'note' => $entry->note,
                    'at' => $entry->created_at?->toIso8601String(),
                ])->values(),
            ];
        }

        return $data;
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

    private static function sellerName(Product $product): ?string
    {
        $seller = $product->seller;

        return $seller?->name;
    }
}
