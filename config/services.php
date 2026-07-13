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

    'discord' => [
        // Local System Logic
        'local' => [
            'bay_assign'    => 1454375087473168405,
            'bay_errors'    => 1454375087473168405,
            'ac_errors'     => 1454375087473168405,
            'error_logs'    => 1454375087473168405,
            'server_logs'   => 1454375087473168405,
            'github_logs'   => 1454375087473168405,
        ],

        // Live Server Channels
        'production' => [
            'bay_assign'    => 1454375050886123550,
            'bay_errors'    => 1485503079439925391,
            'ac_errors'     => 1485796516168990830,
            'error_logs'    => 1454268931467640983,
            'server_logs'   => 1454272548790468639,
            'github_logs'   => 1454272577668124782,
        ]
        
    ]

];
