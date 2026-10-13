<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Turns separate listings of the same item in different colors into one product with a color option each.
 * The other listings are hidden, not deleted, so past orders keep pointing at real products.
 */
class CombineProductColors extends Command
{
    protected $signature = 'products:combine-colors
        {--product=* : Product IDs, in display order. The first one is kept and receives the colors}
        {--color=* : Color name for each product, in the same order}
        {--name= : Name for the combined product}
        {--dry-run : Show the result without saving it}';

    protected $description = 'Combine same-item products that differ only by color into one product with color options';

    public function handle(): int
    {
        $ids = array_map('intval', $this->option('product'));
        $colors = array_map('trim', $this->option('color'));
        if (count($ids) < 2 || count($ids) !== count($colors) || count(array_unique($ids)) !== count($ids)) {
            $this->error('Give at least two different --product IDs and one --color for each, in the same order.');

            return self::INVALID;
        }
        if (in_array('', $colors, true) || count(array_unique(array_map('mb_strtolower', $colors))) !== count($colors)) {
            $this->error('Every color needs a different, non-empty name.');

            return self::INVALID;
        }

        $legacy = Product::usesLegacySchema();
        DB::beginTransaction();
        try {
            $products = Product::with('variants', 'images')->whereIn('id', $ids)->get()->keyBy('id');
            $this->guard($ids, $products, $legacy);
            $primary = $products[$ids[0]];
            $name = trim((string) $this->option('name')) ?: $primary->name;
            $nextImagePosition = (int) $primary->images->max('sort_order') + 1;
            $rows = [];

            foreach ($ids as $index => $id) {
                $product = $products[$id];
                $color = $colors[$index];
                $variant = $this->colorVariant($product, $primary, $color, $legacy);

                if (! $product->is($primary)) {
                    $nextImagePosition = $this->moveImages($product, $primary, $nextImagePosition);
                    $this->moveReferences($product, $primary, $variant);
                    $product->forceFill(['is_active' => false])->saveQuietly();
                }
                $this->labelColorPhoto($product->is($primary) ? $primary : $product, $primary, $name, $color);

                $rows[] = [$id, $product->name, $color, number_format((float) $variant->regular_price, 2), $variant->stock, $product->is($primary) ? 'kept' : 'hidden'];
            }

            $primary->refresh();
            $primary->name = $name;
            if ($legacy) {
                $primary->price = ProductVariant::where('product_id', $primary->id)->where('is_active', true)->min('price');
            }
            $primary->saveQuietly();
            Product::syncStockFromVariants($primary->id);

            $this->table(['Product', 'Was', 'Color', 'Price', 'Stock', 'Listing'], $rows);
            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->warn('Dry run: nothing was saved.');
            } else {
                DB::commit();
                $this->info("Combined into #{$primary->id} \"{$name}\" with ".count($ids).' colors.');
            }
        } catch (RuntimeException $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function guard(array $ids, Collection $products, bool $legacy): void
    {
        $missing = array_diff($ids, $products->keys()->all());
        if ($missing) {
            throw new RuntimeException('Product not found: #'.implode(', #', $missing).'.');
        }
        if ($products->pluck('seller_id')->unique()->count() > 1) {
            throw new RuntimeException('These products belong to different sellers. Only one seller\'s listings can be combined.');
        }
        foreach ($products as $product) {
            // Deployed products have no variants until combined; newer ones carry exactly one default variant.
            if ($product->variants->count() > ($legacy ? 0 : 1)) {
                throw new RuntimeException("#{$product->id} {$product->name} already has options. Combine products that are sold as a single item.");
            }
        }
    }

    private function colorVariant(Product $product, Product $primary, string $color, bool $legacy): ProductVariant
    {
        if ($legacy) {
            return ProductVariant::create([
                'product_id' => $primary->id,
                'sku' => $product->sku,
                'name' => $color,
                'color' => $color,
                'price' => $product->getRawOriginal('price'),
                'stock' => (int) $product->getRawOriginal('stock'),
                'is_active' => true,
            ]);
        }

        $variant = $product->variants->first();
        $variant->forceFill(['product_id' => $primary->id, 'name' => $color, 'options' => ['color' => $color]])->save();

        return $variant;
    }

    private function moveImages(Product $from, Product $to, int $position): int
    {
        $images = $from->images;
        $legacyPhoto = Product::usesLegacySchema() ? $from->getRawOriginal('image_url') : null;
        if ($legacyPhoto && ! $images->contains('path', $legacyPhoto)) {
            ProductImage::create(['product_id' => $to->id, 'path' => $legacyPhoto, 'sort_order' => $position++]);
        }
        foreach ($images as $image) {
            $image->product_id = $to->id;
            $image->sort_order = $position++;
            $image->save();
        }

        return $position;
    }

    /**
     * The product page matches an option to its photo by the "<product> — <color>" alt text.
     */
    private function labelColorPhoto(Product $source, Product $primary, string $name, string $color): void
    {
        // Every photo of the color is tagged so the product page can gray them out when the color sells out.
        $paths = $source->images->pluck('path')->push(Product::usesLegacySchema() ? $source->getRawOriginal('image_url') : null)->filter();
        ProductImage::where('product_id', $primary->id)
            ->where(fn ($query) => $query->whereIn('id', $source->images->pluck('id'))->orWhereIn('path', $paths))
            ->update(['alt_text' => "{$name} — {$color}"]);
    }

    private function moveReferences(Product $from, Product $to, ProductVariant $variant): void
    {
        if (Schema::hasTable('product_reviews')) {
            DB::table('product_reviews')->where('product_id', $from->id)->update(['product_id' => $to->id]);
        }
        if (Schema::hasColumn('cart_items', 'product_id')) {
            DB::table('cart_items')->where('product_id', $from->id)
                ->update(['product_id' => $to->id, 'product_variant_id' => $variant->id]);
        }
        if (Schema::hasTable('wishlists')) {
            $alreadySaved = DB::table('wishlists')->where('product_id', $to->id)->pluck('user_id');
            DB::table('wishlists')->where('product_id', $from->id)->whereIn('user_id', $alreadySaved)->delete();
            DB::table('wishlists')->where('product_id', $from->id)->update(['product_id' => $to->id]);
        }
    }
}
