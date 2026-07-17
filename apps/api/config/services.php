<?php

return [
    'amazon' => [
        'lwa_client_id' => env('AMAZON_LWA_CLIENT_ID'),
        'lwa_client_secret' => env('AMAZON_LWA_CLIENT_SECRET'),
        'application_id' => env('AMAZON_SPAPI_APPLICATION_ID'),
        'redirect_uri' => env('AMAZON_REDIRECT_URI'),
        'default_region' => env('AMAZON_DEFAULT_REGION', 'eu'),
        'sandbox' => env('AMAZON_SPAPI_SANDBOX', false),
        'authorization_draft' => env('AMAZON_SPAPI_DRAFT', true),
        'timeout' => env('AMAZON_SPAPI_TIMEOUT', 30),
        'retry_times' => env('AMAZON_SPAPI_RETRY_TIMES', 3),
        'user_agent' => env('AMAZON_SPAPI_USER_AGENT', 'OopSeller/1.0 (Language=PHP; Platform=Laravel)'),
    ],
    'flipkart' => [
        'client_id' => env('FLIPKART_CLIENT_ID'),
        'client_secret' => env('FLIPKART_CLIENT_SECRET'),
        'redirect_uri' => env('FLIPKART_REDIRECT_URI'),
        // Flipkart's seller sandbox uses api.flipkart.net; production uses the same host.
        'base_url' => env('FLIPKART_BASE_URL', 'https://api.flipkart.net'),
        'timeout' => env('FLIPKART_TIMEOUT', 30),
    ],
    'meesho' => [
        'base_url' => env('MEESHO_BASE_URL', 'https://supplier-api.meesho.com'),
        'timeout' => env('MEESHO_TIMEOUT', 30),
    ],
    'snapdeal' => [
        'base_url' => env('SNAPDEAL_BASE_URL', 'https://apigateway.snapdeal.com'),
        'timeout' => env('SNAPDEAL_TIMEOUT', 30),
    ],
    'rank_provider' => [
        'driver' => env('RANK_PROVIDER', 'null'),
        'api_key' => env('RANK_PROVIDER_API_KEY'),
    ],
];
