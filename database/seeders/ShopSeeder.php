<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $seller = User::where('role', 'seller')
    ->where('status', 'active')
    ->first();

        if (! $seller) {
            return;
        }

        $shops = [
            [
                'name' => 'Maison Élégance',
                'slug' => 'maison-elegance',
                'logo' => null,
                'cover_image' => null,
                'tagline' => 'Refined pieces for everyday elegance.',
                'description' => 'A curated fashion shop featuring timeless clothing and elevated essentials.',
                'featured' => true,
            ],
            [
                'name' => 'Atelier No. 8',
                'slug' => 'atelier-no-8',
                'logo' => null,
                'cover_image' => null,
                'tagline' => 'Modern essentials, thoughtfully selected.',
                'description' => 'Contemporary wardrobe pieces designed for effortless everyday styling.',
                'featured' => true,
            ],
            [
                'name' => 'Luna Studio',
                'slug' => 'luna-studio',
                'logo' => null,
                'cover_image' => null,
                'tagline' => 'Soft silhouettes. Strong character.',
                'description' => 'A collection of feminine and contemporary pieces for modern wardrobes.',
                'featured' => false,
            ],
            [
                'name' => 'North & Thread',
                'slug' => 'north-and-thread',
                'logo' => null,
                'cover_image' => null,
                'tagline' => 'Classic style, modern attitude.',
                'description' => 'Clean, versatile fashion pieces made for everyday wear.',
                'featured' => false,
            ],
        ];

        foreach ($shops as $shop) {
            Shop::updateOrCreate(
                ['slug' => $shop['slug']],
                [
                    'seller_id' => $seller->id,
                    'name' => $shop['name'],
                    'logo' => $shop['logo'],
                    'cover_image' => $shop['cover_image'],
                    'tagline' => $shop['tagline'],
                    'description' => $shop['description'],
                    'featured' => $shop['featured'],
                    'is_active' => true,
                ]
            );
        }
    }
}