<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index()
    {
        return view('index', ['newArrivals' => self::newArrivals()]);
    }

    /** The ten newest in-stock products for the home page. Sign-in and sign-up links open over this page too. */
    public static function newArrivals(): Collection
    {
        if (! Schema::hasTable('products')) {
            return collect();
        }

        $query = Product::withReviewSummary()->visibleToBuyers();

        if (Schema::hasColumn('products', 'stock')) {
            $query->where('stock', '>', 0)
                ->with(['images', 'additionalCategories'])
                ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)])
                ->withSum(['variants as catalog_variant_stock' => fn ($builder) => $builder->where('is_active', true)], 'stock');
        } else {
            $query->whereHas('variants', fn ($builder) => $builder->where('is_active', true)->where('stock', '>', 0))
                ->with(['images', 'defaultVariant', 'category', 'additionalCategories'])
                ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)])
                ->withSum(['variants as catalog_variant_stock' => fn ($builder) => $builder->where('is_active', true)], 'stock');
        }

        return $query->latest()->limit(10)->get();
    }
}
