<?php

/*
 * The mobile app API (routes/api.php, /api/mobile/...).
 */
return [
    // Days a mobile sign-in stays valid; 0 keeps it until the person signs out or changes their password.
    'token_lifetime_days' => (int) env('MOBILE_TOKEN_LIFETIME_DAYS', 90),
];
