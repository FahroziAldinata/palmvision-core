<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'forecasting' => [
        'url' => env('FORECASTING_URL', 'http://forecasting:8000'),
        'api_key' => env('FORECASTING_API_KEY', 'palmvision-dev-key'),
    ],

    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
    ],

    'tileserv' => [
        'url' => env('TILESERV_URL', 'http://tileserv:7800'),
    ],

    'pks' => [
        'webhook_secret' => env('PKS_WEBHOOK_SECRET', 'palmvision-pks-secret-test-key'),
        'api_key' => env('PKS_API_KEY', 'palmvision-pks-api-key-test'),
    ],

];
