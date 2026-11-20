<?php

return [
    'allow_mocks_in_production' => (bool) env('TOPUP_ALLOW_MOCKS_IN_PRODUCTION', false),
    'default_currency' => env('TOPUP_DEFAULT_CURRENCY', 'USD'),
    'default_locale' => env('TOPUP_DEFAULT_LOCALE', 'km'),
    'exchange_rate_usd_to_khr' => (float) env('TOPUP_EXCHANGE_RATE_USD_TO_KHR', 4100.0000),
    'quote_ttl_seconds' => (int) env('TOPUP_QUOTE_TTL_SECONDS', 900),
    'order_payment_ttl_seconds' => (int) env('TOPUP_ORDER_PAYMENT_TTL_SECONDS', 900),
    'quote_signing_secret' => env('TOPUP_QUOTE_SIGNING_SECRET', env('APP_KEY')),

    'payment' => [
        'default_provider' => env('TOPUP_PAYMENT_PROVIDER', 'mock_payway'),
        'fee_bps' => [
            'mock_khqr' => 0,
            'aba_khqr' => 0,
            'aba_payway' => 150, // 1.50%
        ],
        'mock_webhook_secret' => env('TOPUP_MOCK_WEBHOOK_SECRET', 'dev-mock-webhook-secret-change-me'),
        'aba_payway' => [
            'environment' => env('ABA_PAYWAY_ENV', 'sandbox'),
            'base_url' => env('ABA_PAYWAY_BASE_URL', 'https://checkout-sandbox.payway.com.kh'),
            'merchant_id' => env('ABA_PAYWAY_MERCHANT_ID'),
            'api_key' => env('ABA_PAYWAY_API_KEY'),
            'public_key' => env('ABA_PAYWAY_PUBLIC_KEY'),
            'webhook_secret' => env('ABA_PAYWAY_WEBHOOK_SECRET'),
        ],
    ],

    'suppliers' => [
        'default_mode' => env('TOPUP_SUPPLIER_MODE', 'mock'),
        'kas_play' => [
            'base_url' => env('KAS_PLAY_BASE_URL'),
            'api_key' => env('KAS_PLAY_API_KEY'),
            'api_secret' => env('KAS_PLAY_API_SECRET'),
        ],
        'unipin' => [
            'base_url' => env('UNIPIN_BASE_URL'),
            'partner_id' => env('UNIPIN_PARTNER_ID'),
            'secret_key' => env('UNIPIN_SECRET_KEY'),
        ],
    ],

    'storefront_url' => rtrim((string) env('TOPUP_STOREFRONT_URL', 'http://127.0.0.1:3000'), '/'),

    'google_oauth' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI', 'http://127.0.0.1:8000/api/v1/auth/google/callback'),
    ],

    'notifications' => [
        'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'telegram_enabled' => (bool) env('TELEGRAM_NOTIFICATIONS_ENABLED', false),
    ],
];
