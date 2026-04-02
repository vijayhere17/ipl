<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    // ❌ OLD (keep for safety, but not used now)
    'cricapi' => [
        'key' => env('CRIC_API_KEY'),
        'base_url' => env('CRIC_API_BASE_URL'),
    ],

    // ✅ NEW (MAIN API NOW)
    'sportmonks' => [
        'key' => env('SPORTMONKS_API_KEY'),
        'base_url' => env('SPORTMONKS_BASE_URL'),
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

];