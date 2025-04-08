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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'wildberries' => [
        'token' => env('WILDBERRIES_API_TOKEN'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model' => 'deepseek-chat',
    ],

    'gigachat' => [
        'client_id'     => env('GIGACHAT_CLIENT_ID'),
        'client_secret' => env('GIGACHAT_CLIENT_SECRET'),
        'scope'         => env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS'),
    ],

    'proxyapi' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => 'https://api.proxyapi.ru/openai/v1',
        'model' => 'gpt-4o-mini', // или другая модель по вашему выбору
    ],
];
