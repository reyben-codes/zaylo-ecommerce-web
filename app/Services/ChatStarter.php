<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use App\Support\Shop;

/**
 * Opens (or reuses) a buyer's conversation with a seller, from a product or a shop page. Shared by the website
 * (ChatController, ShopController) and the mobile app, so both reach the same conversation and its messages.
 */
class ChatStarter
{
    /** The buyer's conversation with the seller of an active product; it remembers the product it started from. */
    public function forProduct(User $buyer, Product $product): Conversation
    {
        abort_unless($product->is_listed, 404);
        $seller = Product::usesLegacySchema() ? $product->seller : $product->seller?->owner;
        abort_unless($seller && $seller->isActive() && $seller->hasRole('seller') && $seller->hasVerifiedEmail(), 404);
        abort_if((int) $seller->id === (int) $buyer->id, 422, 'You cannot message yourself.');

        return Conversation::firstOrCreate(['buyer_id' => $buyer->id, 'seller_id' => $seller->id], ['product_id' => $product->id]);
    }

    /** The buyer's conversation with a shop's owner. */
    public function forShop(User $buyer, int $shopId): Conversation
    {
        $shop = Shop::find($shopId) ?? abort(404);
        abort_unless($shop->owner->hasVerifiedEmail(), 404);
        abort_if($shop->owner->is($buyer), 422, 'You cannot message yourself.');

        return Conversation::firstOrCreate(['buyer_id' => $buyer->id, 'seller_id' => $shop->owner->id]);
    }
}
