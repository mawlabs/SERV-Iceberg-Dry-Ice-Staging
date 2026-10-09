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

    'google' => [
        'api_key' => env('GOOGLE_API_KEY'),
        'address_api_key' => env('ADDRESS_API_KEY'),
    ],

    'novex' => [
        'api_url' => env('NOVEX_API_URL'),
        'push_url' => env('NOVEX_API_PUSH_URL'),
        'auth_key' => env('NOVEX_AUTH_KEY'),
    ],

    'exact' => [
        'login' => env('EXACT_LOGIN_ID'),
        'transaction_key' => env('EXACT_TRANSACTION_KEY'),
        'payment_url' => env('EXACT_PAYMENT_URL'),
        // Secret from the Exact payment page settings; used to verify x_MD5_Hash on the payment callback.
        'response_key' => env('EXACT_RESPONSE_KEY'),
    ],

    'http_verify' => env('APP_ENV') !== 'local',


];
