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

    'ml' => [
        'enabled' => env('ML_ENABLED', false),

        'ml1' => [
            'base_url' => env('ML1_BASE_URL', 'http://127.0.0.1:8000'),
            'connect_timeout' => (int) env('ML1_CONNECT_TIMEOUT', 1),
            'timeout' => (int) env('ML1_TIMEOUT', 2),
        ],

        'ml2' => [
            'base_url' => env('ML2_BASE_URL', 'http://127.0.0.1:8000'),
            'connect_timeout' => (int) env('ML2_CONNECT_TIMEOUT', 1),
            'timeout' => (int) env('ML2_TIMEOUT', 5),
            'basis_csv' => env('ML2_BASIS_CSV') ?: base_path(
                'ml-monitoring-gizi/ml-monitoring-gizi/app/ml2/model/basis_resep.csv'
            ),
        ],
    ],

];
