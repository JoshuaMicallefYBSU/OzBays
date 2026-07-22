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

    'changelog' => [
        'token' => env('CHANGELOG_TOKEN'),
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
        ],

        // Discord role IDs managed by App\Jobs\DiscordRoleSync — fill these in once the
        // matching roles exist in the Discord server. A null entry is simply skipped.
        'roles' => [
            'Lead Developer' => 1454245065194213479,
            'Developer'      => 1528207402682417263,
            'Maintainer'     => 1454245256764588172,
            'Contributor'    => 1454245362436149350,
            'Pilot'          => 1454245409169080512,
        ],

        // Opt-in news category roles, keyed by the user_preferences column that toggles them.
        'news_roles' => [
            'news_general'       => 1528300580697669734, // General Announcements
            'news_notifications' => 1528300788990869634, // News Articles
            'ozbays_updates'     => 1528300646372081724, // OzBays Updates
        ],

        // Assigned to Discord members who aren't linked to an OzBays account — their nickname
        // is reset to their raw Discord username and any other managed roles are stripped.
        'unlinked_role' => 1528300889008373910,

        // Discord IDs App\Jobs\DiscordRoleSync will never touch. Discord's API rejects nickname/
        // role changes for the guild owner no matter what permissions the bot has (role
        // hierarchy places the owner above everyone, including the bot) — that's a platform
        // restriction, not something fixable from this end, so the owner must manage their
        // own nickname/roles manually. The bot's own account is skipped too — it shouldn't be
        // trying to manage its own membership.
        'excluded_ids' => [
            1448828624621928508, // OzBays bot
            200426385863344129,  // Server owner
        ],

    ]

];
