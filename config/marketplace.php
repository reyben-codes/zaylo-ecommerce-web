<?php

return [
    // Products and options show as "Low stock" at or below this many units. Fixed for every seller.
    'low_stock_threshold' => 10,

    'categories' => [
        'fashion' => 'Fashion',
        'electronics' => 'Electronics',
        'home_living' => 'Home & Living',
        'kitchen_dining' => 'Kitchen & Dining',
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
        'kitchen_dining' => [
            'label' => 'Kitchen & Dining',
            'image' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?w=160&h=160&fit=crop&auto=format',
        ],
    ],

    // Words that flag a product for the admin's compliance review (items that cannot be sold on ZAYLO).
    'prohibited_keywords' => [
        'gun', 'firearm', 'ammunition', 'ammo', 'bullet', 'explosive', 'firecracker', 'pyrotechnic',
        'shabu', 'marijuana', 'cannabis', 'weed', 'drugs', 'vape juice', 'e-cigarette', 'cigarette', 'tobacco',
        'alcohol', 'liquor', 'prescription', 'antibiotic', 'counterfeit', 'replica', 'fake', 'class a', 'pirated',
        'ivory', 'endangered', 'wildlife', 'adult toy', 'pornographic', 'gambling', 'lottery',
    ],
    // Seller categories that cover more than one product category.
    'category_groups' => [
        'fashion' => ['fashion', 'clothing', 'bags', 'shoes', 'watches', 'accessories'],
    ],
    // Swatch colors for common color words in option names; options without a match show their photo or name only.
    'color_swatches' => [
        'black' => '#1f1d1b', 'white' => '#f7f5f2', 'gold' => '#c9a54b', 'silver' => '#c3c6c9', 'pink' => '#f0b6c6',
        'olive' => '#6f7a3a', 'green' => '#4d8a4f', 'red' => '#b8392f', 'blue' => '#3d6fb6', 'navy' => '#24365c',
        'gray' => '#9a9a9a', 'grey' => '#9a9a9a', 'brown' => '#7b5a3c', 'beige' => '#e3d3b5', 'cream' => '#efe6d2',
        'yellow' => '#e6c84a', 'orange' => '#e07b39', 'purple' => '#7d5ba6', 'maroon' => '#6e2430', 'khaki' => '#b8a77a',
        'teal' => '#2f7f7c', 'lavender' => '#b9a7d6', 'tan' => '#c19a6b', 'charcoal' => '#3b3b3d', 'ivory' => '#f6f1e3',
    ],

    // Regions a logistics partner can choose to serve (at least one).
    'service_regions' => [
        'NCR' => 'NCR', 'CAR' => 'CAR', 'I' => 'Region I (Ilocos Region)', 'II' => 'Region II (Cagayan Valley)',
        'III' => 'Region III (Central Luzon)', 'IV-A' => 'Region IV-A (CALABARZON)', 'MIMAROPA' => 'MIMAROPA Region',
        'V' => 'Region V (Bicol Region)', 'VI' => 'Region VI (Western Visayas)', 'NIR' => 'Negros Island Region',
        'VII' => 'Region VII (Central Visayas)', 'VIII' => 'Region VIII (Eastern Visayas)', 'IX' => 'Region IX (Zamboanga Peninsula)',
        'X' => 'Region X (Northern Mindanao)', 'XI' => 'Region XI (Davao Region)', 'XII' => 'Region XII (SOCCSKSARGEN)',
        'XIII' => 'Region XIII (Caraga)', 'BARMM' => 'BARMM',
    ],

    // Colors offered as one-tap labels when a seller adds a product; any other color name can be typed.
    'color_presets' => ['Black', 'White', 'Gray', 'Navy', 'Blue', 'Red', 'Green', 'Pink', 'Beige', 'Brown', 'Yellow', 'Purple'],

    // Who a product is for (the "For" field). Shoppers filtering by one group also see the groups listed for it,
    // e.g. Unisex items appear under Men and Women, and Kids (boys or girls) items under Boys and Girls.
    'genders' => [
        'women' => 'Women',
        'men' => 'Men',
        'unisex' => 'Unisex',
        'girls' => 'Girls',
        'boys' => 'Boys',
        'kids' => 'Kids (boys or girls)',
    ],
    'gender_matches' => [
        'women' => ['women', 'unisex'],
        'men' => ['men', 'unisex'],
        'unisex' => ['unisex'],
        'girls' => ['girls', 'kids'],
        'boys' => ['boys', 'kids'],
        'kids' => ['kids', 'girls', 'boys'],
    ],
    'kids_genders' => ['girls', 'boys', 'kids'],

    // Temporary standard sizes sellers can start from; they can rename, add or remove sizes per color.
    'size_presets' => [
        'shoes' => ['US 6', 'US 7', 'US 8', 'US 9', 'US 10', 'US 11'],
        'clothing' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'fashion' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
    ],
    // Used instead when a product is for girls, boys or kids: toddler and kids clothing, and kids' shoe sizes
    // (C = little kids, Y = big kids).
    'kids_size_presets' => [
        'shoes' => ['10C', '11C', '12C', '13C', '1Y', '2Y', '3Y', '4Y', '5Y', '6Y', '7Y'],
        'clothing' => ['2T', '3T', '4T', '5', '6', '7', '8', '10', '12', '14'],
        'fashion' => ['2T', '3T', '4T', '5', '6', '7', '8', '10', '12', '14'],
    ],

    'fashion_categories' => ['fashion', 'clothing', 'bags', 'shoes', 'watches', 'accessories'],
];
