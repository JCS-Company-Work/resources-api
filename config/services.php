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

];
