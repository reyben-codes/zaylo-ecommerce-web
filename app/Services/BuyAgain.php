<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * "Buy again": puts an earlier order's products back in the buyer's cart at today's price. Products that are no longer
 * on sale or out of stock are skipped, and less is added when less is left.
 */
class BuyAgain
{
    public function __construct(private readonly CartService $carts) {}

    /** @return array{added: array<int, string>, skipped: array<int, string>} product names */
    public function add(User $buyer, Order $order): array
    {
        $added = $skipped = [];
        foreach ($order->items()->with(['product.variants'])->get() as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_listed || $product->is_archived) {
                $skipped[] = $item->product_name;

                continue;
            }
            $variant = $item->product_variant_id ? $product->variants->firstWhere('id', (int) $item->product_variant_id) : null;
            $left = (int) ($variant?->stock ?? $product->stock);
            $quantity = min((int) $item->quantity, $left);
            if ($quantity < 1 || ($item->product_variant_id && (! $variant || ! $variant->is_active))) {
                $skipped[] = $item->product_name;

                continue;
            }
            try {
                $this->carts->add($buyer, $product, $quantity, $variant?->id);
                $added[] = $item->product_name;
            } catch (ValidationException) {
                // Already in the cart up to the stock left, or an option must be chosen again.
                $skipped[] = $item->product_name;
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    public static function message(array $result): string
    {
        if (! $result['added']) {
            return 'None of these products can be bought again right now: they are sold out or no longer on sale.';
        }
        $message = count($result['added']) === 1 ? '1 product was added to your cart.' : count($result['added']).' products were added to your cart.';

        return $result['skipped'] ? $message.' Not available now: '.implode(', ', $result['skipped']).'.' : $message;
    }
}
