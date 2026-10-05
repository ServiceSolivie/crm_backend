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

    'google' => [
        'credentials_path' => env('GOOGLE_CREDENTIALS_PATH', storage_path('app/google/fastline-82b0b-627369a623fe.json')),
        'sheet_id' => env('GOOGLE_SHEET_ID'),
    ],

    'ringover' => [
        'api_key' => env('RINGOVER_API_KEY'),
        'base_url' => env('RINGOVER_BASE_URL', 'https://public-api.ringover.com/v2'),
        'webhook_secret' => env('RINGOVER_WEBHOOK_SECRET'),
        'timeout' => (int) env('RINGOVER_TIMEOUT', 10),
        'sync' => [
            // First run (no previous successful sync): how far back to fetch.
            'initial_hours' => (int) env('RINGOVER_SYNC_INITIAL_HOURS', 3),
            // Never fetch further back than this, even after a very long outage.
            'max_days' => (int) env('RINGOVER_SYNC_MAX_DAYS', 30),
            // Re-fetch a few minutes before the last sync end, for calls still in progress then.
            'overlap_minutes' => 15,
            // Calls are requested in slices of this size to stay within API limits.
            'slice_hours' => 24,
        ],
    ],

    'phone' => [
        // Region used to interpret numbers written without an international prefix.
        'default_region' => env('PHONE_DEFAULT_REGION', 'FR'),
    ],

];
