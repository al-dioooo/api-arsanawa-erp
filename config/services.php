<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
        'storage' => [
            'product_images_bucket' => env('SUPABASE_STORAGE_PRODUCT_IMAGES_BUCKET', 'arsanawa-product-images'),
            'imports_bucket' => env('SUPABASE_STORAGE_IMPORTS_BUCKET', 'arsanawa-imports'),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        // Active driver: "fonnte" (live) or "log" (local/test, no network).
        'driver' => env('WHATSAPP_DRIVER', 'log'),

        // Global kill-switch; per-company settings can still disable individually.
        'enabled' => env('WHATSAPP_ENABLED', false),

        'fonnte' => [
            'base_url' => env('WHATSAPP_FONNTE_BASE_URL', 'https://api.fonnte.com/send'),
            'token' => env('WHATSAPP_FONNTE_TOKEN'),
            'sender' => env('WHATSAPP_FONNTE_SENDER'),
            'timeout' => (int) env('WHATSAPP_FONNTE_TIMEOUT', 15),
        ],
    ],

];
