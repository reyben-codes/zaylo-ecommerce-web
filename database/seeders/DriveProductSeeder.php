<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DriveProductSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = json_decode(
            file_get_contents(database_path('seeders/data/drive-products.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $sellerId = Seller::query()->value('id');

        foreach ($catalog as $item) {
            foreach ($item['images'] as $image) {
                if (! is_file(public_path(ltrim($image, '/')))) {
                    throw new RuntimeException("Missing catalog image: {$image}");
                }
            }
        }

        DB::transaction(function () use ($catalog, $sellerId): void {
            foreach ($catalog as $item) {
                $product = $item['existing']
                    ? Product::where('seller_id', $sellerId)->where('name', $item['name'])->first()
                    : null;

                if ($product) {
                    // The gardening catalog already has prices, stock, and descriptive copy.
                    $product->update(['is_active' => true]);
                } else {
                    $product = Product::updateOrCreate(
                        ['sku' => $item['sku']],
                        [
                            'seller_id' => $sellerId,
                            'name' => $item['name'],
                            'category' => $item['category'],
                            'gender' => $item['gender'],
                            'price' => $item['price'],
                            'stock' => $item['stock'],
                            'low_stock_threshold' => min(5, $item['stock']),
                            'badge' => 'New',
                            'image_url' => $item['images'][0],
                            'is_active' => true,
                        ],
                    );

                    // A source folder can contain distinct products. Remove only source-owned
                    // photos that were previously assigned to this imported listing.
                    $product->images()
                        ->where('path', 'like', '/images/drive-products/%')
                        ->whereNotIn('path', $item['images'])
                        ->delete();
                }

                foreach ($item['images'] as $index => $path) {
                    $product->images()->updateOrCreate(
                        ['path' => $path],
                        [
                            'alt_text' => $item['name'].' - photo '.($index + 1),
                            'sort_order' => $index,
                        ],
                    );
                }
            }
        });
    }
}
