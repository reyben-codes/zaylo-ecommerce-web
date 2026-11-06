<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Placing and cancelling orders, shared by the web storefront (BuyerController) and the mobile API,
 * so pricing (including scheduled sales), stock locking, the ID check and voucher use stay identical.
 * Shipping is charged per seller, by the distance from the hub of the logistics partner the buyer picks to the
 * delivery address (ShippingRates).
 */
class CheckoutService
{
    /** The fee without a partner or a located address; older app builds show it. */
    public const SHIPPING_PER_SELLER = ShippingRates::STANDARD_FEE;

    /** Whether orders record the buyer's logistics partner (after the 2026_11_07_000002 migration). */
    public static function choosesPartner(): bool
    {
        return Shipment::tracksProvider() && Schema::hasColumn('orders', 'logistics_provider_id');
    }

    /** @return Collection<int, LogisticsProvider> partners a buyer can pick at checkout */
    public function partners(): Collection
    {
        return self::choosesPartner() ? LogisticsProvider::acceptingParcels()->get() : collect();
    }

    /**
     * The partner for an order: the buyer's pick, or the nearest one to the address when none was sent (older app
     * builds). Null when no partner takes parcels, and the flat fee applies.
     */
    public function partnerFor(mixed $partnerId, ?Address $address): ?LogisticsProvider
    {
        $partners = $this->partners();
        if ($partners->isEmpty()) {
            return null;
        }
        if (blank($partnerId)) {
            return $address ? ShippingRates::nearest($partners, $address) : $partners->first();
        }
        $partner = $partners->firstWhere('id', (int) $partnerId);
        if (! $partner) {
            throw ValidationException::withMessages(['logistics_provider_id' => 'That logistics partner is not taking parcels right now. Choose another one.']);
        }

        return $partner;
    }

    public function normalizeVoucherCode(?string $code): string
    {
        return Str::upper(trim((string) $code));
    }

    /** The usable free-shipping voucher for a code, or null for an empty code. */
    public function voucher(string $code, bool $lock = false): ?Voucher
    {
        if ($code === '') {
            return null;
        }
        $query = Voucher::where('code', $code);
        $voucher = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $voucher?->isAvailable() || $voucher->type !== 'free_shipping') {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher is invalid or no longer available.']);
        }

        return $voucher;
    }

    /** @param Collection<int, CartItem> $items with product and variant loaded */
    public function quote(Collection $items, ?Voucher $voucher, ?Address $address = null, ?LogisticsProvider $partner = null): array
    {
        $zone = ShippingRates::zone($partner, $address);
        $perSeller = ShippingRates::fee($zone);
        $subtotal = $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
        $shipping = $items->pluck('product.seller_id')->unique()->count() * $perSeller;
        $shippingDiscount = $voucher ? $shipping : 0;

        return [
            'subtotal' => (float) $subtotal,
            'shipping' => (float) $shipping,
            'shipping_discount' => (float) $shippingDiscount,
            'total' => (float) ($subtotal + $shipping - $shippingDiscount),
            'shipping_per_seller' => $perSeller,
            'shipping_zone' => $zone,
        ];
    }

    public function alreadyPlaced(string $idempotencyKey): bool
    {
        return Payment::where('idempotency_key', 'like', $idempotencyKey.'%')->exists();
    }

    /**
     * The items a buyer may not order yet: products the seller marked 18+, while the buyer has no approved ID.
     * Everything else can be ordered without an ID.
     *
     * @param  Collection<int, CartItem>  $items  with product loaded
     * @return Collection<int, CartItem>
     */
    public function itemsNeedingIdentity(User $buyer, Collection $items): Collection
    {
        if ($buyer->isIdentityVerified()) {
            return collect();
        }

        return $items->filter(fn (CartItem $item) => (bool) $item->product?->is_adult_only)->values();
    }

    /** @param  Collection<int, CartItem>  $items  with product loaded */
    public function ensureCanOrder(User $buyer, Collection $items): void
    {
        $restricted = $this->itemsNeedingIdentity($buyer, $items);
        if ($restricted->isNotEmpty()) {
            throw ValidationException::withMessages([
                'identity' => 'Some items are for adults (18+): '.$restricted->pluck('product.name')->unique()->implode(', ')
                    .'. Your ID needs to be approved before you can order them. Upload it in Profile Settings, or leave them out of this order.',
            ]);
        }
    }

    /**
     * Places one order per seller for the selected cart items.
     *
     * @param  array{address_id:int, idempotency_key:string, payment_method?:?string, notes?:?string, quoted_unit_prices?:array}  $input
     * @return Collection<int, Order>
     */
    public function place(User $buyer, array $selectedIds, array $input, string $voucherCode): Collection
    {
        $paymentMethod = $input['payment_method'] ?? 'cod';

        return DB::transaction(function () use ($buyer, $selectedIds, $input, $voucherCode, $paymentMethod) {
            User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $cart = Cart::where('user_id', $buyer->id)->with(['items.product', 'items.variant'])->firstOrFail();
            $itemsToOrder = $cart->items->whereIn('id', $selectedIds);
            if ($itemsToOrder->isEmpty() || $itemsToOrder->count() !== count($selectedIds)) {
                throw ValidationException::withMessages(['cart' => 'Your cart changed. Review your selection and try again.']);
            }
            $this->ensureCanOrder($buyer, $itemsToOrder);
            $voucher = $this->voucher($voucherCode, lock: true);
            $address = Address::where('user_id', $buyer->id)->whereKey($input['address_id'])->firstOrFail();
            if (! $address->isStructured()) {
                throw ValidationException::withMessages(['address_id' => 'Please edit this saved address and select its Philippine locations before checkout.']);
            }
            $partner = $this->partnerFor($input['logistics_provider_id'] ?? null, $address);
            $zone = ShippingRates::zone($partner, $address);
            $perSeller = ShippingRates::fee($zone);

            $orders = collect();
            foreach ($itemsToOrder->groupBy(fn ($item) => $item->product->seller_id ?? 0) as $sellerId => $items) {
                $prepared = collect();
                foreach ($items as $item) {
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $variant = $item->product_variant_id
                        ? ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->firstOrFail()
                        : null;
                    $stock = (int) ($variant?->stock ?? $product->stock);
                    if (! $product->is_listed || $stock < $item->quantity) {
                        throw ValidationException::withMessages(['cart' => "{$product->name} no longer has enough stock."]);
                    }
                    $unitPrice = (float) ($variant ? $product->priceFor($variant) : $product->price);
                    $quoted = $input['quoted_unit_prices'] ?? null;
                    if (is_array($quoted) && (
                        ! isset($quoted[$item->id])
                        || (int) round((float) $quoted[$item->id] * 100) !== (int) round($unitPrice * 100)
                    )) {
                        throw ValidationException::withMessages(['cart' => 'A product price changed since you reviewed checkout. Please review the updated total before placing your order.']);
                    }
                    // Flash sale units are counted as they sell; the row is locked, so two buyers cannot take the last one.
                    if ($product->flashAppliesTo($variant)) {
                        if ($item->quantity > $product->flashRemaining()) {
                            throw ValidationException::withMessages(['cart' => "Only {$product->flashRemaining()} of {$product->name} are left at the flash sale price. Lower the quantity to buy them."]);
                        }
                        Product::whereKey($product->id)->increment('flash_sold', $item->quantity);
                    }
                    $prepared->push(compact('item', 'product', 'variant', 'unitPrice'));
                }

                $subtotal = $prepared->sum(fn ($row) => $row['unitPrice'] * $row['item']->quantity);
                $shippingDiscount = $voucher ? $perSeller : 0;
                $shippingFee = $perSeller - $shippingDiscount;
                $order = Order::create([
                    'order_number' => 'ZAY-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                    'user_id' => $buyer->id, 'seller_id' => $sellerId ?: null, 'status' => 'placed',
                    'subtotal' => $subtotal, 'shipping_fee' => $shippingFee, 'total' => $subtotal + $shippingFee,
                    'voucher_code' => $voucher?->code, 'shipping_discount' => $shippingDiscount,
                    'payment_method' => $paymentMethod, 'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone, 'shipping_address' => $address->formatted(),
                    'notes' => $input['notes'] ?? null, 'placed_at' => now(),
                ] + ParcelTracking::orderPin($address) + (Schema::hasColumn('orders', 'courier_choice')
                    // Only once the 2026_10_26 migration has added the column.
                    ? ['courier_choice' => $input['courier_choice'] ?? Order::DEFAULT_COURIER]
                    : []) + (self::choosesPartner()
                    ? ['logistics_provider_id' => $partner?->id, 'shipping_zone' => $zone]
                    : []));

                foreach ($prepared as $row) {
                    ['item' => $item, 'product' => $product, 'variant' => $variant, 'unitPrice' => $unitPrice] = $row;
                    $order->items()->create([
                        'product_id' => $product->id, 'product_variant_id' => $variant?->id,
                        'seller_id' => $product->seller_id,
                        'product_name' => $product->name.($variant ? ' — '.$variant->name : ''),
                        'sku' => $variant?->sku ?? $product->sku, 'unit_price' => $unitPrice,
                        'quantity' => $item->quantity, 'line_total' => $unitPrice * $item->quantity,
                    ]);
                    $variant ? $variant->decrement('stock', $item->quantity) : $product->decrement('stock', $item->quantity);
                    if ($variant) {
                        Product::syncStockFromVariants($product->id);
                    }
                }

                $order->payment()->create([
                    'provider' => $paymentMethod, 'status' => 'pending', 'amount' => $order->total,
                    'currency' => 'PHP', 'idempotency_key' => $input['idempotency_key'].'-'.$order->id,
                ]);
                $order->statusHistory()->create([
                    'changed_by' => $buyer->id, 'status' => 'placed', 'note' => 'Order placed by buyer.',
                ]);
                $orders->push($order);
            }
            $voucher?->increment('used_count');
            $cart->items()->whereIn('id', $selectedIds)->delete();

            return $orders;
        });
    }

    /** Buyers can cancel only while the order waits for the seller; once the seller confirms it, it is being prepared. */
    public const BUYER_CANCELLABLE = ['placed'];

    /**
     * Cancels a buyer's order while the seller has not confirmed it yet, putting the stock back. The status is read
     * again under a row lock, so a page opened before the seller confirmed cannot cancel it afterwards.
     */
    public function cancel(Order $order, User $buyer): void
    {
        abort_unless($order->user_id === $buyer->id, 403);

        DB::transaction(function () use ($order, $buyer) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->status, self::BUYER_CANCELLABLE, true)) {
                throw ValidationException::withMessages(['order' => $lockedOrder->status === 'cancelled'
                    ? "Order {$lockedOrder->order_number} is already cancelled."
                    : "The seller already confirmed order {$lockedOrder->order_number}, so it can no longer be cancelled. Message the seller if something is wrong."]);
            }
            $lockedOrder->load(['items', 'shipment', 'payment']);
            foreach ($lockedOrder->items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->quantity);
                    Product::syncStockFromVariants((int) $item->product_id);
                } elseif ($item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }
            $lockedOrder->update(['status' => 'cancelled']);
            $lockedOrder->shipment?->update(['status' => 'cancelled']);
            $lockedOrder->payment?->update(['status' => 'cancelled']);
            $lockedOrder->statusHistory()->create([
                'changed_by' => $buyer->id, 'status' => 'cancelled', 'note' => 'Cancelled by buyer.',
            ]);
        });
    }
}
