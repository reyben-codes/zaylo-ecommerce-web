<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        $ownsProduct = Product::usesLegacySchema()
            ? $product->seller_id === $user->id
            : $product->seller?->user_id === $user->id;

        return $user->hasRole('admin') || ($user->hasRole('seller') && $ownsProduct);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
