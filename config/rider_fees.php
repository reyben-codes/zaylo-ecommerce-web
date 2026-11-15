<?php

return [
    'base_per_leg' => (float) env('RIDER_BASE_PER_LEG', 10),
    'per_km' => (float) env('RIDER_PER_KM', 2),
    'per_kg' => (float) env('RIDER_PER_KG', 3),
    'shipping_budget_percent' => (float) env('RIDER_SHIPPING_BUDGET_PERCENT', 80),
    'routing_url' => env('ROAD_ROUTING_URL', 'https://router.project-osrm.org'),
];
