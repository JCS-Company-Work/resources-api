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

    'api_keys' => [
        'resource_api' => env('RESOURCE_API_TOKEN'),
        'combined_data_api' => env('COMBINED_DATA_API_TOKEN'),
        'product_finder_api' => env('PRODUCT_FINDER_API_TOKEN'),
        'tm_colours_api' => env('TM_COLOURS_API_TOKEN'),
        'samples_api' => env('SAMPLES_API_TOKEN'),
        'florimuk' => env('FLORIMUK_API_TOKEN'),
    ],
    'hub_cache_purge' => [
        'url' => env('HUB_CACHE_PURGE_URL'),
        'token' => env('HUB_CACHE_PURGE_TOKEN'),
    ],
    'google_sheets' => [
        'application_name' => env('GOOGLE_SHEETS_APPLICATION_NAME'),
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'range' => env('GOOGLE_SHEETS_RANGE'),
        'attributes_range' => env('GOOGLE_SHEETS_ATTRIBUTES_RANGE'),
        'locations_range' => env('GOOGLE_SHEETS_LOCATIONS_RANGE'),
        'credentials' => storage_path('app/'.env('GOOGLE_SHEETS_CREDENTIALS')),
    ],

];
