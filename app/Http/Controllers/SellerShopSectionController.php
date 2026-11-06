<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShopSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Seller Center > Products > Shop sections: the seller's own groupings ("New Arrivals", "Bundle Deals") shown as tabs
 * on their shop page only. Sellers add, rename, reorder, hide and delete them and choose the products in each; a
 * product's edit page can also pick its sections. The marketplace category menu is never affected.
 */
class SellerShopSectionController extends Controller
{
    public function index()
    {
        abort_unless(ShopSection::available(), 404);
        $shopId = $this->shopId();
        $sections = ShopSection::forShop($shopId)
            ->withCount(['products as product_count' => fn ($products) => $products->notArchived()])
            ->with(['products' => fn ($products) => $products->notArchived()->select('products.id')])
            ->get();

        $products = Product::where('seller_id', $shopId)->notArchived()
            ->with(Product::usesLegacySchema() ? [] : ['images'])
            ->orderBy('name')->get();

        return view('seller.shop-sections', [
            'sections' => $sections,
            'products' => $products,
            'storeName' => auth()->user()->sellerStoreName(),
            'shopUrl' => route('shops.show', $shopId),
            'counts' => [
                'active' => Product::where('seller_id', $shopId)->notArchived()->count(),
                'archived' => Product::where('seller_id', $shopId)->whereNotNull('archived_at')->count(),
            ],
            'max' => ShopSection::MAX_PER_SHOP,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(ShopSection::available(), 404);
        $shopId = $this->shopId();
        $name = $this->validatedName($request, $shopId);
        if (ShopSection::where('shop_id', $shopId)->count() >= ShopSection::MAX_PER_SHOP) {
            throw ValidationException::withMessages(['name' => 'You can have up to '.ShopSection::MAX_PER_SHOP.' shop sections. Delete one to add another.']);
        }

        $section = ShopSection::create([
            'shop_id' => $shopId,
            'name' => $name,
            'position' => (int) ShopSection::where('shop_id', $shopId)->max('position') + 1,
            'is_visible' => true,
        ]);

        return redirect()->route('seller.products.sections', ['open' => $section->id])->withFragment('section-'.$section->id)
            ->with('status', "Section \"{$section->name}\" added. Choose the products to show in it.");
    }

    /** Rename, show or hide, and (when the product list was on the form) choose the section's products. */
    public function update(Request $request, ShopSection $section)
    {
        $this->authorizeSection($section);
        $name = $this->validatedName($request, $section->shop_id, $section);
        $request->validate([
            'is_visible' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('seller_id', $section->shop_id)],
        ], ['product_ids.*.exists' => 'Choose products from your own shop.']);

        DB::transaction(function () use ($request, $section, $name) {
            $section->update(['name' => $name, 'is_visible' => $request->boolean('is_visible')]);
            // Archived products stay linked (they come back if restored); only the listed ones are replaced.
            if ($request->boolean('products_submitted')) {
                $archived = $section->products()->whereNotNull('archived_at')->pluck('products.id')->all();
                $section->products()->sync([...array_map('intval', $request->input('product_ids', [])), ...$archived]);
            }
        });

        return redirect()->route('seller.products.sections')->withFragment('section-'.$section->id)
            ->with('status', "Section \"{$section->name}\" saved.");
    }

    public function move(Request $request, ShopSection $section)
    {
        $this->authorizeSection($section);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($section, $direction) {
            // Number them 1..n first, so sections created at the same position still move one step at a time.
            $ordered = ShopSection::forShop($section->shop_id)->lockForUpdate()->get()->values();
            $index = $ordered->search(fn (ShopSection $item) => $item->id === $section->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swap < 0 || $swap >= $ordered->count()) {
                return;
            }
            $moved = $ordered->all();
            [$moved[$index], $moved[$swap]] = [$moved[$swap], $moved[$index]];
            foreach ($moved as $position => $item) {
                if ($item->position !== $position + 1) {
                    $item->update(['position' => $position + 1]);
                }
            }
        });

        return redirect()->route('seller.products.sections')->withFragment('section-'.$section->id)
            ->with('status', "Moved \"{$section->name}\" {$direction}.");
    }

    /** Removes the section only; its products stay in the shop and in their other sections. */
    public function destroy(ShopSection $section)
    {
        $this->authorizeSection($section);
        $section->delete();

        return redirect()->route('seller.products.sections')->with('status', "Section \"{$section->name}\" deleted. Its products are still in your shop.");
    }

    private function validatedName(Request $request, int $shopId, ?ShopSection $ignore = null): string
    {
        $request->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('name')))]);
        $name = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:'.ShopSection::NAME_MAX],
        ], [
            'name.required' => 'Give the section a name.',
            'name.min' => 'Use at least 2 characters for the section name.',
            'name.max' => 'Keep the section name to '.ShopSection::NAME_MAX.' characters or fewer.',
        ])['name'];

        $taken = ShopSection::where('shop_id', $shopId)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => "You already have a section called \"{$name}\"."]);
        }

        return $name;
    }

    private function authorizeSection(ShopSection $section): void
    {
        abort_unless($section->shop_id === $this->shopId(), 404);
    }

    private function shopId(): int
    {
        return Product::usesLegacySchema() ? (int) auth()->id() : (int) auth()->user()->sellerShop()->id;
    }
}
