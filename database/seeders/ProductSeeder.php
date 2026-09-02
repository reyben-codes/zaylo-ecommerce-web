<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Clothing
            ['name' => 'Wool Blend Blazer',      'category' => 'clothing',    'gender' => 'women', 'price' => 4250, 'original_price' => 5500,  'badge' => 'New',         'image_url' => 'https://images.unsplash.com/photo-1539008835657-9e8e9680c956?w=400&h=533&fit=crop&auto=format', 'stock' => 20, 'description' => 'A luxurious wool blend blazer perfect for any occasion.'],
            ['name' => 'Cashmere Sweater',        'category' => 'clothing',    'gender' => 'women', 'price' => 3800, 'original_price' => 4800,  'badge' => 'Sale',        'image_url' => 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=400&h=533&fit=crop&auto=format', 'stock' => 15, 'description' => 'Soft cashmere sweater in a relaxed fit.'],
            ['name' => 'Tailored Trousers',       'category' => 'clothing',    'gender' => 'men',   'price' => 2900, 'original_price' => null,   'badge' => null,          'image_url' => 'https://images.unsplash.com/photo-1594938298603-c8148c4b4d36?w=400&h=533&fit=crop&auto=format', 'stock' => 30, 'description' => 'Classic tailored trousers with a slim fit.'],
            ['name' => 'Silk Evening Dress',      'category' => 'clothing',    'gender' => 'women', 'price' => 6800, 'original_price' => null,   'badge' => 'Best Seller', 'image_url' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=400&h=533&fit=crop&auto=format', 'stock' => 10, 'description' => 'Elegant silk evening dress with a flowing silhouette.'],
            ['name' => 'Linen Button-Up Shirt',   'category' => 'clothing',    'gender' => 'men',   'price' => 1950, 'original_price' => 2400,  'badge' => 'Sale',        'image_url' => 'https://images.unsplash.com/photo-1602810316693-3667c854239a?w=400&h=533&fit=crop&auto=format', 'stock' => 25, 'description' => 'Breathable linen shirt for warm days.'],

            // Bags
            ['name' => 'Leather Tote Bag',        'category' => 'bags',        'gender' => 'women', 'price' => 3750, 'original_price' => 4500,  'badge' => 'Sale',        'image_url' => 'https://images.unsplash.com/photo-1560343090-f0409e92791a?w=400&h=533&fit=crop&auto=format', 'stock' => 12, 'description' => 'Spacious genuine leather tote with interior pockets.'],
            ['name' => 'Crossbody Bag',            'category' => 'bags',        'gender' => 'women', 'price' => 2200, 'original_price' => null,   'badge' => null,          'image_url' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=400&h=533&fit=crop&auto=format', 'stock' => 18, 'description' => 'Compact crossbody bag with adjustable strap.'],
            ['name' => 'Canvas Weekend Bag',       'category' => 'bags',        'gender' => 'men',   'price' => 2800, 'original_price' => null,   'badge' => 'New',         'image_url' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400&h=533&fit=crop&auto=format', 'stock' => 8,  'description' => 'Durable canvas weekend bag with leather trim.'],
            ['name' => 'Quilted Chain Bag',        'category' => 'bags',        'gender' => 'women', 'price' => 4100, 'original_price' => null,   'badge' => 'Best Seller', 'image_url' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=400&h=533&fit=crop&auto=format', 'stock' => 6,  'description' => 'Classic quilted bag with gold chain strap.'],

            // Shoes
            ['name' => 'Classic Leather Sneakers','category' => 'shoes',       'gender' => 'unisex','price' => 2890, 'original_price' => null,   'badge' => 'Best Seller', 'image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&h=533&fit=crop&auto=format', 'stock' => 22, 'description' => 'Minimalist leather sneakers that go with everything.'],
            ['name' => 'Leather Ankle Boots',     'category' => 'shoes',       'gender' => 'women', 'price' => 4800, 'original_price' => 5600,  'badge' => 'Sale',        'image_url' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=400&h=533&fit=crop&auto=format', 'stock' => 14, 'description' => 'Sleek leather ankle boots with block heel.'],
            ['name' => 'Oxford Dress Shoes',      'category' => 'shoes',       'gender' => 'men',   'price' => 3600, 'original_price' => null,   'badge' => null,          'image_url' => 'https://images.unsplash.com/photo-1614252369475-531eba835eb1?w=400&h=533&fit=crop&auto=format', 'stock' => 16, 'description' => 'Polished oxford shoes for formal occasions.'],
            ['name' => 'Suede Loafers',           'category' => 'shoes',       'gender' => 'unisex','price' => 3100, 'original_price' => 3800,  'badge' => 'New',         'image_url' => 'https://images.unsplash.com/photo-1600269452121-4f2416e55c28?w=400&h=533&fit=crop&auto=format', 'stock' => 20, 'description' => 'Soft suede loafers with a cushioned sole.'],

            // Watches
            ['name' => 'Minimalist Watch',        'category' => 'watches',     'gender' => 'unisex','price' => 5200, 'original_price' => null,   'badge' => null,          'image_url' => 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=400&h=533&fit=crop&auto=format', 'stock' => 10, 'description' => 'Clean dial minimalist watch on a leather strap.'],
            ['name' => 'Gold Chronograph',        'category' => 'watches',     'gender' => 'men',   'price' => 8400, 'original_price' => null,   'badge' => 'Best Seller', 'image_url' => 'https://images.unsplash.com/photo-1547996160-81dfa63595aa?w=400&h=533&fit=crop&auto=format', 'stock' => 5,  'description' => 'Precision chronograph with gold-tone case.'],
            ['name' => 'Rose Gold Ladies Watch',  'category' => 'watches',     'gender' => 'women', 'price' => 4600, 'original_price' => 5200,  'badge' => 'Sale',        'image_url' => 'https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=400&h=533&fit=crop&auto=format', 'stock' => 8,  'description' => 'Elegant rose gold watch with mother-of-pearl dial.'],

            // Accessories
            ['name' => 'Silk Scarf',              'category' => 'accessories', 'gender' => 'women', 'price' => 1400, 'original_price' => null,   'badge' => 'New',         'image_url' => 'https://images.unsplash.com/photo-1601924994987-69e26d50dc26?w=400&h=533&fit=crop&auto=format', 'stock' => 30, 'description' => 'Luxurious printed silk scarf.'],
            ['name' => 'Leather Belt',            'category' => 'accessories', 'gender' => 'men',   'price' => 980,  'original_price' => null,   'badge' => null,          'image_url' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400&h=533&fit=crop&auto=format', 'stock' => 40, 'description' => 'Full-grain leather belt with silver buckle.'],
            ['name' => 'Gold Hoop Earrings',      'category' => 'accessories', 'gender' => 'women', 'price' => 1200, 'original_price' => 1500,  'badge' => 'Sale',        'image_url' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=400&h=533&fit=crop&auto=format', 'stock' => 25, 'description' => 'Classic 18k gold-plated hoop earrings.'],
            ['name' => 'Wool Flat Cap',           'category' => 'accessories', 'gender' => 'men',   'price' => 850,  'original_price' => null,   'badge' => null,          'image_url' => 'https://images.unsplash.com/photo-1521369909029-2afed882baaa?w=400&h=533&fit=crop&auto=format', 'stock' => 18, 'description' => 'Heritage wool flat cap in herringbone tweed.'],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
