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

];
