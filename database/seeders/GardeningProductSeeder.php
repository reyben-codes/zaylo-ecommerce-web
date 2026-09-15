<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class GardeningProductSeeder extends Seeder
{
    public function run(): void
    {
        $sellerId = User::where('role', 'seller')->value('id');

        $products = [
            [
                'name' => '1.2L Long Spout Watering Can',
                'category' => 'home_living',
                'gender' => null,
                'price' => 499,
                'original_price' => 649,
                'badge' => 'New',
                'image_url' => '/images/1.2L Long Spout Plastic Watering Can Green.webp',
                'stock' => 54,
                'low_stock_threshold' => 9,
                'weight_grams' => 320,
                'description' => 'A compact 1.2-liter watering can with a slim stainless-steel spout for controlled watering around leaves and delicate houseplants.',
                'images' => [
                    ['/images/1.2L Long Spout Plastic Watering Can Green.webp', 'Green 1.2L long spout watering can', 0],
                    ['/images/1.2L Long Spout Plastic Watering Can Pink.webp', 'Pink watering can being used on indoor plants', 1],
                    ['/images/1.2L Long Spout Plastic Watering Can White.webp', 'White 1.2L long spout watering can', 2],
                ],
                'variants' => [
                    ['ZAY-WCAN-12-GRN', 'Green', null, 'Green', 18],
                    ['ZAY-WCAN-12-PNK', 'Pink', null, 'Pink', 18],
                    ['ZAY-WCAN-12-WHT', 'White', null, 'White', 18],
                ],
            ],
            [
                'name' => 'Bully Tools 14-Gauge Round Point Shovel',
                'category' => 'sports_outdoors',
                'gender' => null,
                'price' => 1599,
                'original_price' => 1899,
                'badge' => 'Best Seller',
                'image_url' => '/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle.webp',
                'stock' => 16,
                'low_stock_threshold' => 4,
                'weight_grams' => 2300,
                'description' => 'A heavy-duty 14-gauge round-point shovel with a reinforced fiberglass handle, designed for digging, planting, and moving compact soil.',
                'images' => [
                    ['/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle.webp', 'Full view of the Bully Tools round point shovel', 0],
                    ['/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle1.webp', 'Close-up of the 14-gauge shovel blade', 1],
                    ['/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle2.webp', 'Close-up of the reinforced shovel handle', 2],
                    ['/images/Bully Tools 14-Gauge Round Point Shovel D-Grip Handle3.webp', 'Round point shovel digging into soil', 3],
                ],
            ],
            [
                'name' => 'COOLJOB Gardening Gloves - 6 Pairs',
                'category' => 'sports_outdoors',
                'gender' => 'women',
                'price' => 899,
                'original_price' => 1099,
                'badge' => 'Sale',
                'image_url' => '/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green.webp',
                'stock' => 36,
                'low_stock_threshold' => 6,
                'weight_grams' => 540,
                'description' => 'Six pairs of breathable garden work gloves with flexible latex-coated palms, reinforced fingertips, and elastic wrists for a secure fit.',
                'images' => [
                    ['/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green.webp', 'Red and green COOLJOB gardening gloves', 0],
                    ['/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green1.webp', 'Latex palm coating and reinforced fingertips', 1],
                    ['/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green2.webp', 'Breathable glove backing and flexible wrist', 2],
                    ['/images/COOLJOB 6 Pairs Gardening Gloves for Women Work Gloves with Breathable Rubber Coating Gloves for Work Red & Green3.webp', 'COOLJOB gardening glove size chart', 3],
                ],
                'variants' => [
                    ['ZAY-CJGLV-6-S', 'Small', 'S', 'Red & Green', 12],
                    ['ZAY-CJGLV-6-M', 'Medium', 'M', 'Red & Green', 12],
                    ['ZAY-CJGLV-6-L', 'Large', 'L', 'Red & Green', 12],
                ],
            ],
            [
                'name' => 'Westward 6-Inch Steel Blade Garden Hoe',
                'category' => 'sports_outdoors',
                'gender' => null,
                'price' => 1299,
                'original_price' => null,
                'badge' => 'New',
                'image_url' => '/images/Garden Hoe,6 in Steel Blade Westward 2mvt3.webp',
                'stock' => 14,
                'low_stock_threshold' => 4,
                'weight_grams' => 1700,
                'description' => 'A durable garden hoe with a six-inch steel blade and long wood handle for cultivating soil, edging beds, and clearing weeds.',
                'images' => [
                    ['/images/Garden Hoe,6 in Steel Blade Westward 2mvt3.webp', 'Westward garden hoe with six-inch steel blade', 0],
                ],
            ],
        ];

        foreach ($products as $data) {
            $images = $data['images'];
            $variants = $data['variants'] ?? [];
            unset($data['images'], $data['variants']);

            $product = Product::updateOrCreate(
                ['seller_id' => $sellerId, 'name' => $data['name']],
                $data + ['seller_id' => $sellerId, 'is_active' => true],
            );

            foreach ($images as [$path, $altText, $sortOrder]) {
                $product->images()->updateOrCreate(
                    ['path' => $path],
                    ['alt_text' => $altText, 'sort_order' => $sortOrder],
                );
            }

            foreach ($variants as [$sku, $name, $size, $color, $stock]) {
                $product->variants()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $name,
                        'size' => $size,
                        'color' => $color,
                        'price' => $data['price'],
                        'stock' => $stock,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
