<?php

declare(strict_types=1);

$apiKey = env('EGYPTPOST_API_KEY');
$mode = env('EGYPTPOST_MODE');

if (! is_string($mode) || ! in_array($mode, ['partner', 'track_only'], true)) {
    $mode = is_string($apiKey) && $apiKey !== '' ? 'partner' : 'track_only';
}

return [
    'driver' => 'egyptpost',

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | partner    — create/label/return/exchange via your B2B partner gateway.
    | track_only — official TrackTrace only (no public merchant create API).
    |
    */
    'mode' => $mode,

    /*
    |--------------------------------------------------------------------------
    | Partner gateway (private B2B middleware)
    |--------------------------------------------------------------------------
    */
    'base_url' => env('EGYPTPOST_BASE_URL', 'https://api.egyptpost.org/v1'),
    'timeout' => (int) env('EGYPTPOST_TIMEOUT', 20),
    'api_key' => $apiKey,
    'token' => $apiKey,
    'username' => env('EGYPTPOST_USERNAME'),
    'password' => env('EGYPTPOST_PASSWORD'),
    'default_service' => env('EGYPTPOST_DEFAULT_SERVICE', 'standard'),

    /*
    |--------------------------------------------------------------------------
    | Official public TrackTrace (egyptpost.gov.eg)
    |--------------------------------------------------------------------------
    */
    'track_url' => env(
        'EGYPTPOST_TRACK_URL',
        'https://egyptpost.gov.eg/ar-eg/TrackTrace/GetShipmentDetails'
    ),
    'track_portal_url' => env(
        'EGYPTPOST_TRACK_PORTAL_URL',
        'https://egyptpost.gov.eg/ar-eg/TrackTrace?Barcode={barcode}'
    ),
    'user_agent' => env('EGYPTPOST_USER_AGENT', 'ShipBridge-EgyptPost/0.2'),

    'status_map' => [
        'REGISTERED' => 'created',
        'ACCEPTED' => 'created',
        'PICKED_UP' => 'picked_up',
        'DISPATCHED' => 'in_transit',
        'IN_TRANSIT' => 'in_transit',
        'ARRIVED' => 'in_transit',
        'OUT_FOR_DELIVERY' => 'out_for_delivery',
        'DELIVERED' => 'delivered',
        'RETURNED' => 'returned',
        'FAILED' => 'exception',
        'UNDELIVERED' => 'exception',
        // Arabic hints occasionally seen on gov portal
        'تم التسليم' => 'delivered',
        'قيد التوصيل' => 'out_for_delivery',
        'تم القبول' => 'created',
    ],
];
