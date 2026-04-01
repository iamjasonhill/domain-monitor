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

    'brain' => [
        'base_url' => env('BRAIN_BASE_URL'),
        'api_key' => env('BRAIN_API_KEY'),
    ],

    'synergy' => [
        'api_url' => env(
            'SYNERGY_WHOLESALE_API_URL',
            env('SYNERGY_API_URL', 'https://api.synergywholesale.com')
        ),
    ],

    'google' => [
        'safe_browsing_key' => env('GOOGLE_SAFE_BROWSING_KEY'),
        'search_console' => [
            'api_base_url' => env('GOOGLE_SEARCH_CONSOLE_API_BASE_URL', 'https://www.googleapis.com'),
            'inspection_base_url' => env('GOOGLE_SEARCH_CONSOLE_INSPECTION_BASE_URL', 'https://searchconsole.googleapis.com'),
            'token_url' => env('GOOGLE_SEARCH_CONSOLE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
            'access_token' => env('GOOGLE_SEARCH_CONSOLE_ACCESS_TOKEN'),
            'refresh_token' => env('GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN'),
            'client_id' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET'),
            'analytics_row_limit' => (int) env('GOOGLE_SEARCH_CONSOLE_ANALYTICS_ROW_LIMIT', 250),
            'inspection_url_limit' => (int) env('GOOGLE_SEARCH_CONSOLE_INSPECTION_URL_LIMIT', 10),
        ],
    ],

    'matomo' => [
        'base_url' => env('MATOMO_BASE_URL', 'https://stats.redirection.com.au'),
        'token_auth' => env('MATOMO_TOKEN_AUTH'),
    ],

    'domain_monitor' => [
        'brain_api_key' => env('BRAIN_API_KEY'),
        'ops_api_key' => env('OPS_API_KEY'),
        'fleet_control_api_key' => env('FLEET_CONTROL_API_KEY'),
    ],

];
