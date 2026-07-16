<?php

declare(strict_types=1);

return [
    'driver' => 'egyptpost',
    'base_url' => env('EGYPTPOST_BASE_URL', 'https://api.egyptpost.org/v1'),
    'timeout' => (int) env('EGYPTPOST_TIMEOUT', 20),
    'api_key' => env('EGYPTPOST_API_KEY'),
    'token' => env('EGYPTPOST_API_KEY'),
    'status_map' => [
        'REGISTERED' => 'created',
        'DISPATCHED' => 'in_transit',
        'ARRIVED' => 'in_transit',
        'OUT_FOR_DELIVERY' => 'out_for_delivery',
        'DELIVERED' => 'delivered',
        'RETURNED' => 'returned',
        'FAILED' => 'exception',
    ],
];
