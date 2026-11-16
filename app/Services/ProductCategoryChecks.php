<?php

namespace App\Services;

use App\Models\Product;

class ProductCategoryChecks
{
    public static function suggestion(string $name): ?string
    {
        foreach (config('product_categories.rules', []) as $rule) {
            if (preg_match('~'.$rule['pattern'].'~iu', $name)) return $rule['category'];
        }
        return null;
    }

    public static function mismatch(string $name, array $categories, array $customTags = []): ?string
    {
        $suggested = self::suggestion($name);
        if (! $suggested) return null;
        $group = collect(config('marketplace.category_groups'))->search(fn ($keys) => in_array($suggested, $keys, true));
        $allowed = array_merge([$group], config('marketplace.category_groups.'.$group, []));
        if (! in_array($suggested, $categories, true) || array_diff($categories, $allowed) || array_diff(array_keys($customTags), [$group])) {
            return 'Category mismatch: this title suggests '.config('marketplace.categories.'.$suggested).'. Remove unrelated categories before approval.';
        }
        return null;
    }

    public static function forProduct(Product $product): ?string
    {
        return self::mismatch($product->name, $product->category_keys, $product->custom_tags ?? []);
    }
}
