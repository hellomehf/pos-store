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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payway' => [
        'merchant_id' => env('PAYWAY_MERCHANT_ID', 'ec476985'),
        'endpoint' => env('PAYWAY_ENDPOINT', 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase'),
    ],

    'aba' => [
        'merchant_id' => env('ABA_MERCHANT_ID', '1522146'),
    ],

    // ABA PayWay Checkout 2.0 — loaded by App\Services\Aba\AbaConfig
    'aba_payway' => [
        'merchant_id' => env('PAYWAY_MERCHANT_ID'),
        'api_key' => env('PAYWAY_API_KEY'),
        'purchase_url' => env('PAYWAY_PURCHASE_URL', 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase'),
        'env' => env('PAYWAY_ENV', 'sandbox'),
        'return_url' => env('PAYWAY_RETURN_URL', ''),
        'cancel_url' => env('PAYWAY_CANCEL_URL', ''),
        'continue_success_url' => env('PAYWAY_CONTINUE_SUCCESS_URL', ''),
        // QR API (Display dynamic QR) — see ABA QR API docs.
        'qr_lifetime' => max(3, (int) env('PAYWAY_QR_LIFETIME', 15)),
        'qr_callback_url' => env('PAYWAY_QR_CALLBACK_URL', ''),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'bot_secret' => env('TELEGRAM_BOT_SECRET'),
        'group_chat_id' => env('TELEGRAM_GROUP_CHAT_ID'),
    ],
];
