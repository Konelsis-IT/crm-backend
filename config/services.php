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

    /*
    | Konelsis AI Service (D-192, ai-service planning record 29). The CRM calls
    | it only from the backend with a Bearer token + HMAC-SHA256 signature
    | (client id e.g. "crm-local"). Secrets come from .env only. Plain HTTP is
    | accepted only for a loopback development URL.
    */
    'ai_service' => [
        'base_url' => env('AI_SERVICE_URL'),
        'client_id' => env('AI_SERVICE_CLIENT_ID', 'crm-local'),
        'token' => env('AI_SERVICE_TOKEN'),
        'secret' => env('AI_SERVICE_SECRET'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 60),
        'connect_timeout' => (int) env('AI_SERVICE_CONNECT_TIMEOUT', 10),
        // Pending readings older than this are closed as failed (worker not running, lost job).
        'stale_after_minutes' => (int) env('AI_SERVICE_STALE_AFTER_MINUTES', 20),
    ],

];
