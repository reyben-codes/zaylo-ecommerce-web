<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Placing and cancelling orders, shared by the web storefront (BuyerController) and the mobile API,
 * so pricing (including scheduled sales), stock locking, the ID check and voucher use stay identical.
 */
class CheckoutService
{
    public const SHIPPING_PER_SELLER = 120.00;

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
    public function quote(Collection $items, ?Voucher $voucher): array
    {
        $subtotal = $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
        $shipping = $items->pluck('product.seller_id')->unique()->count() * self::SHIPPING_PER_SELLER;
        $shippingDiscount = $voucher ? $shipping : 0;

        return [
            'subtotal' => (float) $subtotal,
            'shipping' => (float) $shipping,
            'shipping_discount' => (float) $shippingDiscount,
            'total' => (float) ($subtotal + $shipping - $shippingDiscount),
        ];
    }

    public function alreadyPlaced(string $idempotencyKey): bool
    {
        return Payment::where('idempotency_key', 'like', $idempotencyKey.'%')->exists();
    }

    /** Buyers need an approved ID before they can order. */
    public function ensureCanOrder(User $buyer): void
    {
        if (! $buyer->isIdentityVerified()) {
            throw ValidationException::withMessages([
                'identity' => 'Your ID needs to be approved before you can place an order. Upload it in Profile Settings.',
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
        $this->ensureCanOrder($buyer);
        $paymentMethod = $input['payment_method'] ?? 'cod';

        return DB::transaction(function () use ($buyer, $selectedIds, $input, $voucherCode, $paymentMethod) {
            User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $cart = Cart::where('user_id', $buyer->id)->with(['items.product', 'items.variant'])->firstOrFail();
            $itemsToOrder = $cart->items->whereIn('id', $selectedIds);
            if ($itemsToOrder->isEmpty() || $itemsToOrder->count() !== count($selectedIds)) {
                throw ValidationException::withMessages(['cart' => 'Your cart changed. Review your selection and try again.']);
            }
            $voucher = $this->voucher($voucherCode, lock: true);
            $address = Address::where('user_id', $buyer->id)->whereKey($input['address_id'])->firstOrFail();
            if (! $address->isStructured()) {
                throw ValidationException::withMessages(['address_id' => 'Please edit this saved address and select its Philippine locations before checkout.']);
            }

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
                    $prepared->push(compact('item', 'product', 'variant', 'unitPrice'));
                }

                $subtotal = $prepared->sum(fn ($row) => $row['unitPrice'] * $row['item']->quantity);
                $shippingDiscount = $voucher ? self::SHIPPING_PER_SELLER : 0;
                $shippingFee = self::SHIPPING_PER_SELLER - $shippingDiscount;
                $order = Order::create([
                    'order_number' => 'ZAY-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                    'user_id' => $buyer->id, 'seller_id' => $sellerId ?: null, 'status' => 'placed',
                    'subtotal' => $subtotal, 'shipping_fee' => $shippingFee, 'total' => $subtotal + $shippingFee,
                    'voucher_code' => $voucher?->code, 'shipping_discount' => $shippingDiscount,
                    'payment_method' => $paymentMethod, 'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone, 'shipping_address' => $address->formatted(),
                    'notes' => $input['notes'] ?? null, 'placed_at' => now(),
                    'courier_choice' => $input['courier_choice'] ?? 'zaylo_express',
                ]);

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

    /** Cancels a buyer's order while it is still placed or confirmed, putting the stock back. */
    public function cancel(Order $order, User $buyer): void
    {
        abort_unless($order->user_id === $buyer->id, 403);

        DB::transaction(function () use ($order, $buyer) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->status, ['placed', 'confirmed'], true)) {
                throw ValidationException::withMessages(['order' => 'This order can no longer be cancelled.']);
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
