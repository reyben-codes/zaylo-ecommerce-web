<?php

return [
    'secret_key' => env('PAYMONGO_SECRET_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    'live' => (bool) env('PAYMONGO_LIVE', false),
    'admin_user_id' => env('ESCROW_ADMIN_USER_ID'),
];
