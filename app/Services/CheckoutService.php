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
use App\Models\VoucherClaim;
use App\Support\BuyerOrderTabs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Placing and cancelling orders, shared by the web storefront (BuyerController) and the mobile API,
 * so pricing (including scheduled sales), stock locking, the ID check and voucher use stay identical.
 * Buyers pay shipping per seller. Sellers choose the registered logistics partner when preparing pickup.
 */
class CheckoutService
{
    /** The fee without a partner or a located address; older app builds show it. */
    public const SHIPPING_PER_SELLER = ShippingRates::STANDARD_FEE;

    /** Whether orders can store the logistics partner selected by the seller. */
    public static function choosesPartner(): bool
    {
        return Shipment::tracksProvider() && Schema::hasColumn('orders', 'logistics_provider_id');
    }

    /** @return Collection<int, LogisticsProvider> registered partners available for fulfilment */
    public function partners(): Collection
    {
        return self::choosesPartner() ? LogisticsProvider::acceptingParcels()->get() : collect();
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
        if (! $voucher?->isAvailable() || ! array_key_exists($voucher->type, Voucher::TYPES) || ($voucher->needsOptIn() && ! Vouchers::ready())) {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher is invalid or no longer available.']);
        }

        return $voucher;
    }

    /**
     * A discount voucher works only on shops that joined it (and that reach its minimum spend); free shipping works on
     * every shop (or only the specified shop if targeted). Refuses a voucher that would take nothing off this cart.
     */
    public function ensureVoucherApplies(?Voucher $voucher, Collection $items, ?User $buyer = null): void
    {
        if (! $voucher) {
            return;
        }

        $this->ensureVoucherCartApplies($voucher, $items);

        if ($buyer === null && auth()->check()) {
            $buyer = auth()->user();
        }

        if ($buyer) {
            if ($voucher->claimedBy($buyer)) {
                $claim = VoucherClaim::where('voucher_id', $voucher->id)
                    ->where('user_id', $buyer->id)
                    ->whereNotNull('used_at')
                    ->first();
                if ($claim) {
                    throw ValidationException::withMessages([
                        'voucher_code' => 'You have already used this voucher.',
                    ]);
                }
            } else {
                if ($voucher->isFullyClaimed()) {
                    throw ValidationException::withMessages([
                        'voucher_code' => 'This voucher has already been claimed by another buyer.',
                    ]);
                }
                app(Vouchers::class)->claim($voucher, $buyer);
            }
        }

    }

    private function ensureVoucherCartApplies(Voucher $voucher, Collection $items): void
    {
        $inCart = $items->pluck('product.seller_id')->map(fn ($id) => (int) $id)->unique();
        if ($voucher->isFreeShipping()) {
            if ($voucher->seller_id && ! $inCart->contains((int) $voucher->seller_id)) {
                $shopName = app(Vouchers::class)->shopName((int) $voucher->seller_id);
                throw ValidationException::withMessages([
                    'voucher_code' => "This voucher only applies to items from {$shopName}.",
                ]);
            }
            if ($voucher->min_spend) {
                $applicableSpend = $voucher->seller_id
                    ? (float) $items->filter(fn ($item) => (int) ($item->product->seller_id ?? 0) === (int) $voucher->seller_id)->sum(fn ($item) => $item->unitPrice() * $item->quantity)
                    : (float) $items->sum(fn ($item) => $item->unitPrice() * $item->quantity);
                if ($applicableSpend < $voucher->min_spend) {
                    throw ValidationException::withMessages([
                        'voucher_code' => 'Spend at least ₱'.number_format((float) $voucher->min_spend, 2).' to use this free shipping voucher.',
                    ]);
                }
            }

            return;
        }

        if (app(Vouchers::class)->cartDiscounts($voucher, $items)) {
            return;
        }

        $joined = app(Vouchers::class)->joinedShops($voucher);
        if ($voucher->seller_id) {
            $shopName = app(Vouchers::class)->shopName((int) $voucher->seller_id);
            if (! $inCart->contains((int) $voucher->seller_id)) {
                throw ValidationException::withMessages([
                    'voucher_code' => "This voucher only applies to items from {$shopName}.",
                ]);
            }
            if ($voucher->min_spend) {
                throw ValidationException::withMessages([
                    'voucher_code' => 'Spend at least ₱'.number_format((float) $voucher->min_spend, 2)." at {$shopName} to use this voucher.",
                ]);
            }
        }

        throw ValidationException::withMessages(['voucher_code' => $inCart->intersect($joined)->isEmpty()
            ? 'None of the shops in your order joined this voucher.'
            : 'Spend at least ₱'.number_format((float) $voucher->min_spend, 2).' at a shop that joined to use this voucher.']);
    }

    /**
     * Active vouchers that can be displayed or applied at checkout for this cart's items.
     *
     * @param  Collection<int, CartItem>  $items
     * @return Collection<int, array<string, mixed>>
     */
    public function availableVouchersFor(Collection $items, ?User $buyer = null): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        if ($buyer === null && auth()->check()) {
            $buyer = auth()->user();
        }

        $vouchersService = app(Vouchers::class);
        $sellerIds = $items->pluck('product.seller_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $subtotal = (float) $items->sum(fn ($item) => $item->unitPrice() * $item->quantity);
        $shopSubtotals = $items->groupBy(fn ($item) => (int) ($item->product->seller_id ?? 0))
            ->map(fn ($group) => (float) $group->sum(fn ($item) => $item->unitPrice() * $item->quantity));

        $vouchers = Voucher::where('is_active', true)->latest('id')->get();

        return $vouchers->filter(function (Voucher $v) use ($sellerIds) {
            if (! $v->isAvailable()) {
                return false;
            }
            if ($v->isFreeShipping()) {
                return ! $v->seller_id || $sellerIds->contains((int) $v->seller_id);
            }
            if ($v->seller_id) {
                return $sellerIds->contains((int) $v->seller_id);
            }
            $joined = app(Vouchers::class)->joinedShops($v);

            return $sellerIds->intersect($joined)->isNotEmpty();
        })->map(function (Voucher $v) use ($items, $shopSubtotals, $subtotal, $vouchersService, $buyer) {
            $canApply = true;
            $reasonCannotApply = null;
            $estimatedDiscount = 0.0;
            $shopName = $v->seller_id ? $vouchersService->shopName((int) $v->seller_id) : 'All shops';

            $isClaimed = $buyer ? $v->claimedBy($buyer) : false;
            $claimsCount = $v->claims()->count();
            $remainingClaims = $v->usage_limit !== null ? max(0, (int) $v->usage_limit - $claimsCount) : null;
            $isFullyClaimed = $v->usage_limit !== null && $claimsCount >= $v->usage_limit;
            $canClaim = (bool) ($buyer && ! $isClaimed && ! $isFullyClaimed && $v->isAvailable());

            if ($isFullyClaimed && ! $isClaimed) {
                $canApply = false;
                $reasonCannotApply = 'Fully claimed';
            }

            if ($v->isFreeShipping()) {
                $applicableSpend = $v->seller_id ? ($shopSubtotals->get((int) $v->seller_id) ?? 0.0) : $subtotal;
                if ($v->min_spend && $applicableSpend < $v->min_spend) {
                    $canApply = false;
                    $shortfall = $v->min_spend - $applicableSpend;
                    $reasonCannotApply = 'Add ₱'.number_format($shortfall, 2).' more';
                }
            } else {
                $joined = $vouchersService->joinedShops($v);
                if ($v->seller_id && ! in_array((int) $v->seller_id, $joined, true)) {
                    $canApply = false;
                    $reasonCannotApply = 'Shop has not joined';
                } else {
                    $applicableSpend = $v->seller_id ? ($shopSubtotals->get((int) $v->seller_id) ?? 0.0) : $subtotal;
                    if ($v->min_spend && $applicableSpend < $v->min_spend) {
                        $canApply = false;
                        $shortfall = $v->min_spend - $applicableSpend;
                        $reasonCannotApply = 'Add ₱'.number_format($shortfall, 2).' more';
                    } else {
                        $discounts = $vouchersService->cartDiscounts($v, $items);
                        $estimatedDiscount = round(array_sum($discounts), 2);
                        if ($estimatedDiscount <= 0) {
                            $canApply = false;
                            $reasonCannotApply = 'No items eligible';
                        }
                    }
                }
            }

            if ($buyer && $v->claims()->where('user_id', $buyer->id)->whereNotNull('used_at')->exists()) {
                $canApply = false;
                $reasonCannotApply = 'Already used';
            }

            return [
                'id' => $v->id,
                'code' => $v->code,
                'name' => $v->name,
                'type' => $v->type,
                'is_free_shipping' => $v->isFreeShipping(),
                'offer_label' => match ($v->type) {
                    'free_shipping' => 'Free Shipping',
                    'percent' => rtrim(rtrim(number_format((float) $v->value, 2), '0'), '.').'% OFF',
                    default => '₱'.number_format((float) $v->value, 2).' OFF',
                },
                'description' => $v->describe(),
                'min_spend' => $v->min_spend ? (float) $v->min_spend : null,
                'expires_at' => $v->expires_at?->timezone('Asia/Manila')->format('M j, Y'),
                'can_apply' => $canApply,
                'reason_cannot_apply' => $reasonCannotApply,
                'estimated_discount' => $estimatedDiscount,
                'shop_name' => $shopName,
                'is_claimed' => $isClaimed,
                'can_claim' => $canClaim,
                'is_fully_claimed' => $isFullyClaimed,
                'remaining_claims' => $remainingClaims,
                'usage_limit' => $v->usage_limit,
            ];
        })->values();
    }

    /** @param Collection<int, CartItem> $items with product and variant loaded */
    public function quote(Collection $items, ?Voucher $voucher, ?Address $address = null, ?LogisticsProvider $partner = null, string $paymentMethod = 'cod'): array
    {
        $zone = ShippingRates::zone($partner, $address);
        $perSeller = ShippingRates::fee($zone);
        $subtotal = $items->sum(fn (CartItem $item) => $item->unitPrice() * $item->quantity);
        $shipping = $items->pluck('product.seller_id')->unique()->count() * $perSeller;
        // Funding is recorded per seller at placement; the buyer receives the same shipping discount.
        if ($voucher?->isFreeShipping()) {
            $sellerIdsInCart = $items->pluck('product.seller_id')->map(fn ($id) => (int) $id)->unique();
            $shippingDiscount = $voucher->seller_id
                ? ($sellerIdsInCart->contains((int) $voucher->seller_id) ? $perSeller : 0)
                : $shipping;
        } else {
            $shippingDiscount = 0;
        }
        $voucherDiscount = round(array_sum(app(Vouchers::class)->cartDiscounts($voucher, $items)), 2);

        return [
            'subtotal' => (float) $subtotal,
            'shipping' => (float) $shipping,
            'shipping_discount' => (float) $shippingDiscount,
            'voucher_discount' => (float) $voucherDiscount,
            'total' => (float) ($subtotal + $shipping - $shippingDiscount - $voucherDiscount),
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
            $this->ensureVoucherApplies($voucher, $itemsToOrder, $buyer);
            $joinedShops = $voucher ? app(Vouchers::class)->joinedShops($voucher) : [];
            $address = Address::where('user_id', $buyer->id)->whereKey($input['address_id'])->firstOrFail();
            if (! $address->isStructured()) {
                throw ValidationException::withMessages(['address_id' => 'Please edit this saved address and select its Philippine locations before checkout.']);
            }
            // Buyer-supplied partner and courier fields from older clients never assign delivery.
            $partner = null;
            $zone = ShippingRates::zone($partner, $address);
            $perSeller = ShippingRates::fee($zone);

            $orders = collect();
            foreach ($itemsToOrder->groupBy(fn ($item) => $item->product->seller_id ?? 0) as $sellerId => $items) {
                if ($paymentMethod === 'gcash' && GcashPayments::ready() && ! GcashPayments::account(GcashPayments::sellerUser($items->first()->product))) {
                    throw ValidationException::withMessages(['payment_method' => 'The seller of '.$items->first()->product->name.' has not set up GCash yet. Choose Cash on Delivery for this order.']);
                }
                $prepared = collect();
                foreach ($items as $item) {
                    $product = Product::whereKey($item->product->getKey())->lockForUpdate()->firstOrFail();
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
                $isFreeShipping = $voucher?->isFreeShipping() && (! $voucher->seller_id || (int) $sellerId === (int) $voucher->seller_id);
                $shippingDiscount = $isFreeShipping ? $perSeller : 0;
                $shippingFee = $perSeller - $shippingDiscount;
                // The shop pays a discount voucher it joined; nothing comes off shops that did not join.
                $voucherDiscount = app(Vouchers::class)->discountFor($voucher, (int) $sellerId, (float) $subtotal, $joinedShops);
                $usesVoucher = $voucher && ($isFreeShipping || $voucherDiscount > 0);
                $sellerFundsShipping = $isFreeShipping && $voucher->funded_by === 'seller';
                $breakdown = Settlement::deliveryBreakdown(
                    max(0, (float) $subtotal - (float) $voucherDiscount),
                    (float) $shippingFee,
                    $isFreeShipping && ! $sellerFundsShipping ? (float) $shippingDiscount : 0,
                    $sellerFundsShipping ? (float) $shippingDiscount : 0,
                    $partner?->riderRate() ?? RiderWallet::COMMISSION,
                );
                $breakdown['logistics_selection'] = 'seller';
                if ($breakdown['seller_earnings'] < 0) {
                    throw ValidationException::withMessages(['voucher_code' => 'Add more items from this shop to cover its free-shipping voucher, or remove the voucher.']);
                }
                $order = Order::create([
                    'order_number' => 'ZAY-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                    'user_id' => $buyer->id, 'seller_id' => $sellerId ?: null, 'status' => 'placed',
                    'subtotal' => $subtotal, 'shipping_fee' => $shippingFee, 'total' => $subtotal + $shippingFee - $voucherDiscount,
                    'voucher_code' => $usesVoucher ? $voucher->code : null, 'shipping_discount' => $shippingDiscount,
                    'payment_method' => $paymentMethod, 'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone, 'shipping_address' => $address->formatted(),
                    'notes' => $input['notes'] ?? null, 'placed_at' => now(),
                ] + (Schema::hasColumn('orders', 'settlement_breakdown') ? ['settlement_breakdown' => $breakdown] : [])
                  + (Schema::hasColumn('orders', 'voucher_discount') ? ['voucher_discount' => $voucherDiscount] : [])
                  + ParcelTracking::orderPin($address) + (Schema::hasColumn('orders', 'courier_choice')
                    // Only once the 2026_10_26 migration has added the column.
                    ? ['courier_choice' => null]
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

                $payment = $order->payment()->create([
                    'provider' => $paymentMethod, 'status' => 'pending', 'amount' => $order->total,
                    'currency' => 'PHP', 'idempotency_key' => $input['idempotency_key'].'-'.$order->id,
                    // The request never supplies this: it is calculated from
                    // locked product rows and the server's fixed shipping fee.
                    'payload' => [
                        'cod_amount' => (float) $order->total,
                        'commission_breakdown' => $breakdown,
                    ],
                ]);
                // GCash goes to the seller's own GCash: the reference waits for the seller's check (GcashPayments).
                if ($paymentMethod === 'gcash' && GcashPayments::ready()) {
                    $reference = GcashPayments::normalize($input['gcash_references'][$sellerId] ?? null);
                    $payment->forceFill(GcashPayments::checkoutPayment($reference !== '' ? $reference : null))->save();
                }
                $order->statusHistory()->create([
                    'changed_by' => $buyer->id, 'status' => 'placed', 'note' => 'Order placed by buyer.',
                ]);
                $orders->push($order);
            }
            if ($voucher && $buyer) {
                VoucherClaim::where('voucher_id', $voucher->id)
                    ->where('user_id', $buyer->id)
                    ->whereNull('used_at')
                    ->latest('id')
                    ->first()
                    ?->update(['used_at' => now()]);
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
    public function cancel(Order $order, User $buyer, ?string $reason = null, ?string $note = null): void
    {
        abort_unless($order->user_id === $buyer->id, 403);

        DB::transaction(function () use ($order, $buyer, $reason, $note) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->status, self::BUYER_CANCELLABLE, true)) {
                throw ValidationException::withMessages(['order' => $lockedOrder->status === 'cancelled'
                    ? "Order {$lockedOrder->order_number} is already cancelled."
                    : "The seller already confirmed order {$lockedOrder->order_number}, so it can no longer be cancelled. Message the seller if something is wrong."]);
            }
            $label = BuyerOrderTabs::reasonLabel($reason);
            $this->cancelLocked($lockedOrder, $buyer->id, 'Cancelled by buyer'.($label ? ': '.$label.($note ? ' ('.$note.')' : '') : '').'.', $reason, $note);
        });
    }

    /** A GCash order the buyer never paid (App\Services\GcashPayments::expireDue): cancelled with its stock restored. */
    public function cancelUnpaid(Order $order, string $note): void
    {
        DB::transaction(function () use ($order, $note) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($locked && in_array($locked->status, self::BUYER_CANCELLABLE, true)) {
                $this->cancelLocked($locked, null, $note, 'payment_issue', null);
            }
        });
    }

    /** Puts the stock back and marks the (already locked) order, its parcel and payment cancelled. */
    private function cancelLocked(Order $lockedOrder, ?int $actorId, string $note, ?string $reason, ?string $reasonNote): void
    {
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
        // The reason (BuyerOrderTabs::CANCEL_REASONS) and when, which later moves it to Purchase history.
        if (Schema::hasColumn('orders', 'cancel_reason')) {
            $lockedOrder->forceFill(['cancel_reason' => $reason, 'cancel_note' => $reasonNote, 'cancelled_at' => now()])->save();
        }
        $lockedOrder->shipment?->update(['status' => 'cancelled']);
        $lockedOrder->payment?->update(['status' => 'cancelled']);
        $lockedOrder->statusHistory()->create(['changed_by' => $actorId, 'status' => 'cancelled', 'note' => $note]);
    }
}
