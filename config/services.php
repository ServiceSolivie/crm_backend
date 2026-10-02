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
        'webhook_secret' => env('GOOGLE_SHEETS_WEBHOOK_SECRET'),
    ],

    'google_ads' => [
        'webhook_key' => env('GOOGLE_ADS_WEBHOOK_KEY'),
    ],

    'acquisition' => [
        'system_user_id' => env('ACQUISITION_SYSTEM_USER_ID'),
    ],

    'plane' => [
        'base_url' => env('PLANE_BASE_URL'),
        'api_key' => env('PLANE_API_KEY'),
        'workspace' => env('PLANE_WORKSPACE'),
        'project_id' => env('PLANE_PROJECT_ID'),
        'feedback_state_id' => env('PLANE_FEEDBACK_STATE_ID'),
        'feedback_labels' => [
            'issue' => env('PLANE_ISSUE_LABEL_ID'),
            'suggestion' => env('PLANE_SUGGESTION_LABEL_ID'),
        ],
        'payment_failure' => [
            'state_id' => env('PLANE_PAYMENT_FAILURE_STATE_ID'),
            'label_id' => env('PLANE_PAYMENT_LABEL_ID'),
        ],
    ],

    // Online payments. profile_id is the business profile (pro_…), not the
    // connector account id (mca_…).
    'hyperswitch' => [
        'base_url' => env('HYPERSWITCH_BASE_URL', 'https://sandbox.hyperswitch.io'),
        'api_key' => env('HYPERSWITCH_API_KEY'),
        'profile_id' => env('HYPERSWITCH_PROFILE_ID'),
        'connector' => env('HYPERSWITCH_CONNECTOR', 'sogecommerce'),
        'currency' => env('HYPERSWITCH_CURRENCY', 'EUR'),
        // Base URL of the CRM frontend: the client comes back to {return_url}/paiement/{token}
        'return_url' => env('HYPERSWITCH_RETURN_URL', env('FRONTEND_URL', env('APP_URL', 'http://localhost'))),
        // How long the result page stays readable after the client came back (minutes)
        'result_page_ttl' => (int) env('HYPERSWITCH_RESULT_PAGE_TTL', 120),
        // Response and connection time limits of the HTTP client (seconds)
        'timeout' => (int) env('HYPERSWITCH_TIMEOUT', 20),
        'connect_timeout' => (int) env('HYPERSWITCH_CONNECT_TIMEOUT', 5),
    ],

];
