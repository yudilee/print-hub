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

    // Notification alert channels (Task 3.7)
    'notification_channels' => [
        'email' => [
            'enabled' => env('NOTIFICATION_EMAIL_ENABLED', false),
            'recipients' => explode(',', (string) env('NOTIFICATION_EMAIL_RECIPIENTS', '')),
        ],
        'slack' => [
            'enabled' => env('NOTIFICATION_SLACK_ENABLED', false),
            'webhook_url' => env('NOTIFICATION_SLACK_WEBHOOK_URL', ''),
            'channel' => env('NOTIFICATION_SLACK_CHANNEL', '#print-hub-alerts'),
        ],
        'telegram' => [
            'enabled' => env('NOTIFICATION_TELEGRAM_ENABLED', false),
            'bot_token' => env('NOTIFICATION_TELEGRAM_BOT_TOKEN', ''),
            'chat_id' => env('NOTIFICATION_TELEGRAM_CHAT_ID', ''),
        ],
    ],

    // Alert thresholds (Task 3.7)
    'alert_thresholds' => [
        'agent_offline_minutes' => env('ALERT_AGENT_OFFLINE_MINUTES', 5),
        'job_failure_rate' => env('ALERT_JOB_FAILURE_RATE', 3),
        'job_failure_window_minutes' => env('ALERT_JOB_FAILURE_WINDOW', 5),
        'key_rotation_days' => env('ALERT_KEY_ROTATION_DAYS', 7),
    ],

];
