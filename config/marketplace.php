<?php

return [
    'seller_managed_delivery' => env('SELLER_MANAGED_DELIVERY', true),

    'categories' => [
        'fashion' => 'Fashion',
        'electronics' => 'Electronics',
        'home_living' => 'Home & Living',
        'beauty_health' => 'Beauty & Health',
        'groceries' => 'Groceries',
        'sports_outdoors' => 'Sports & Outdoors',
        'toys_hobbies' => 'Toys & Hobbies',
        'automotive' => 'Automotive',

        // Existing fashion inventory remains supported.
        'clothing' => 'Clothing',
        'bags' => 'Bags',
        'shoes' => 'Shoes',
        'watches' => 'Watches',
        'accessories' => 'Accessories',
    ],

    'browse_categories' => [
        'fashion' => [
            'label' => 'Fashion',
            'image' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?w=160&h=160&fit=crop&auto=format',
        ],
        'electronics' => [
            'label' => 'Electronics',
            'image' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=160&h=160&fit=crop&auto=format',
        ],
        'home_living' => [
            'label' => 'Home & Living',
            'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=160&h=160&fit=crop&auto=format',
        ],
        'beauty_health' => [
            'label' => 'Beauty & Health',
            'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=160&h=160&fit=crop&auto=format',
        ],
        'groceries' => [
            'label' => 'Groceries',
            'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=160&h=160&fit=crop&auto=format',
        ],
        'sports_outdoors' => [
            'label' => 'Sports',
            'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=160&h=160&fit=crop&auto=format',
        ],
        'toys_hobbies' => [
            'label' => 'Toys & Hobbies',
            'image' => 'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=160&h=160&fit=crop&auto=format',
        ],
        'automotive' => [
            'label' => 'Automotive',
            'image' => 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?w=160&h=160&fit=crop&auto=format',
        ],
    ],

    // Swatch colors for common color words in option names; options without a match show their photo or name only.
    'color_swatches' => [
        'black' => '#1f1d1b', 'white' => '#f7f5f2', 'gold' => '#c9a54b', 'silver' => '#c3c6c9', 'pink' => '#f0b6c6',
        'olive' => '#6f7a3a', 'green' => '#4d8a4f', 'red' => '#b8392f', 'blue' => '#3d6fb6', 'navy' => '#24365c',
        'gray' => '#9a9a9a', 'grey' => '#9a9a9a', 'brown' => '#7b5a3c', 'beige' => '#e3d3b5', 'cream' => '#efe6d2',
        'yellow' => '#e6c84a', 'orange' => '#e07b39', 'purple' => '#7d5ba6',
    ],

    // Temporary standard sizes sellers can start from; they can rename, add or remove sizes per color.
    'size_presets' => [
        'shoes' => ['US 6', 'US 7', 'US 8', 'US 9', 'US 10', 'US 11'],
        'clothing' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'fashion' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
    ],

    'fashion_categories' => ['fashion', 'clothing', 'bags', 'shoes', 'watches', 'accessories'],
];
