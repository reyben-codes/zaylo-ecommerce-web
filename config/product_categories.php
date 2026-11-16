<?php

// Ordered, conservative title checks shared by PHP and the seller's form.
// Accessories take priority over device names. Unknown titles are left to human review.
return [
    'rules' => [
        ['category' => 'phone_accessories', 'pattern' => '\b(?:iphone|smartphone|mobile phone|galaxy)\b.*\b(?:case|cover|charger|screen protector|holder|mount)\b|\b(?:case|cover|charger|screen protector|holder|mount)\b.*\b(?:iphone|smartphone|mobile phone|galaxy)\b'],
        ['category' => 'computer_accessories', 'pattern' => '\b(?:macbook|laptop|ipad|tablet)\b.*\b(?:case|cover|sleeve|charger|keyboard|stand)\b|\b(?:case|cover|sleeve|charger|keyboard|stand)\b.*\b(?:macbook|laptop|ipad|tablet)\b'],
        ['category' => 'mobile_phones', 'pattern' => '\b(?:iphone\s*(?:[0-9]{1,2})?|smartphones?|mobile phones?|samsung galaxy\s+[aszmf][0-9]+)\b'],
        ['category' => 'tablets', 'pattern' => '\b(?:ipad|galaxy tab|android tablet)\b'],
        ['category' => 'laptops', 'pattern' => '\b(?:macbook|laptop|chromebook)\b'],
        ['category' => 'headphones_earbuds', 'pattern' => '\b(?:airpods|earbuds|headphones)\b'],
        ['category' => 'smartwatches', 'pattern' => '\b(?:apple watch|smartwatch)\b'],
        ['category' => 'gaming_consoles', 'pattern' => '\b(?:playstation|xbox|nintendo switch)\b'],
        ['category' => 'rice_cookers', 'pattern' => '\brice cooker\b'],
        ['category' => 'air_fryers', 'pattern' => '\bair fryer\b'],
        ['category' => 'storage_bins', 'pattern' => '\bstorage bins?\b'],
    ],
];
