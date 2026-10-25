<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Buyer cart rules shared by the web storefront (BuyerController) and the mobile API.
 */
class CartService
{
    public function cartFor(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Adds a product (or one of its options) to the buyer's cart, merging with a line already there.
     * A product with several options needs one chosen; a single option is picked automatically.
     */
    public function add(User $user, Product $product, int $quantity, ?int $variantId = null): CartItem
    {
        abort_unless($product->is_listed, 404);
        $legacyCart = Schema::hasColumn('cart_items', 'product_id');
        $variant = $variantId
            ? $product->variants()->whereKey($variantId)->where('is_active', true)->firstOrFail()
            : ($product->variants()->where('is_active', true)->count() === 1
                ? $product->variants()->where('is_active', true)->first()
                : null);
        if (! $variant && (! $legacyCart || $product->variants()->where('is_active', true)->exists())) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Please choose an available option before adding this product to your cart.',
            ]);
        }
        $available = (int) ($variant?->stock ?? $product->stock);
        $cart = $this->cartFor($user);
        $itemQuery = $cart->items();
        if ($legacyCart) {
            $itemQuery->where('product_id', $product->id);
            $variant
                ? $itemQuery->where('product_variant_id', $variant->id)
                : $itemQuery->whereNull('product_variant_id');
        } else {
            $itemQuery->where('product_variant_id', $variant->id);
        }
        $item = $itemQuery->first();
        $newQuantity = ($item?->quantity ?? 0) + $quantity;

        if ($newQuantity > $available) {
            throw ValidationException::withMessages(['quantity' => "Only {$available} item(s) are available."]);
        }
        if ($item) {
            $item->update(['quantity' => $newQuantity]);

            return $item;
        }

        $data = ['product_variant_id' => $variant?->id, 'quantity' => $quantity];
        if ($legacyCart) {
            $data['product_id'] = $product->id;
        }

        return $cart->items()->create($data);
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        $item->load(['product', 'variant']);
        if ($quantity > $item->availableStock()) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity exceeds available stock.']);
        }
        $item->update(['quantity' => $quantity]);
    }
}
