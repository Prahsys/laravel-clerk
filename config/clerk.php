<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Prahsys API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'sandbox_mode' => env('PRAHSYS_SANDBOX_MODE', true),
        'sandbox_url' => env('PRAHSYS_SANDBOX_URL', 'https://sandbox-api.prahsys.com'),
        'production_url' => env('PRAHSYS_PRODUCTION_URL', 'https://api.prahsys.com'),
        'sandbox_api_key' => env('PRAHSYS_SANDBOX_API_KEY'),
        'production_api_key' => env('PRAHSYS_PRODUCTION_API_KEY'),
        'merchant_id' => env('PRAHSYS_MERCHANT_ID'),
        'timeout' => env('PRAHSYS_API_TIMEOUT', 30),
        'connect_timeout' => env('PRAHSYS_CONNECT_TIMEOUT', 10),
        'max_retries' => env('PRAHSYS_MAX_RETRIES', 3),
        'retry_delay' => env('PRAHSYS_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'enabled' => env('PRAHSYS_WEBHOOKS_ENABLED', true),
        'route' => env('PRAHSYS_WEBHOOK_ROUTE', '/webhooks/prahsys'),
        'secret' => env('PRAHSYS_WEBHOOK_SECRET'),
        'tolerance' => env('PRAHSYS_WEBHOOK_TOLERANCE', 300), // 5 minutes
        'max_attempts' => env('PRAHSYS_WEBHOOK_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    */
    'payment_methods' => [
        'default' => 'pay_session',
        'available' => [
            'pay_portal' => [
                'enabled' => true,
                'description' => 'Hosted payment pages',
            ],
            'pay_session' => [
                'enabled' => true,
                'description' => 'Embedded payment forms',
            ],
            'direct_pay' => [
                'enabled' => false, // Requires Expert Mode
                'description' => 'Direct API payment processing',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    */
    'database' => [
        'sessions_table' => 'clerk_sessions',
        'webhook_events_table' => 'clerk_webhook_events',
        'payments_table' => 'clerk_payments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'prefix' => 'clerk',
        'middleware' => ['web'],
        'webhook_middleware' => ['api', 'clerk.webhook.verify'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'webhook_processing' => env('PRAHSYS_WEBHOOK_QUEUE', 'default'),
        'payment_processing' => env('PRAHSYS_PAYMENT_QUEUE', 'default'),
    ],
];