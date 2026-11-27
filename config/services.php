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

    /*
    | SMS / WhatsApp gateways. driver "log" only records messages (nothing is sent).
    | driver "http" calls your provider's API. {to} and {message} are replaced in the URL / body.
    | Works with most Indian providers (MSG91, Fast2SMS, Textlocal, Interakt, WATI, AiSensy ...).
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'method' => env('SMS_HTTP_METHOD', 'get'),        // get or post
        'url' => env('SMS_HTTP_URL'),                     // e.g. https://api.provider.com/send?key=XXX&to={to}&msg={message}
        'body' => env('SMS_HTTP_BODY'),                   // POST JSON template, e.g. {"to":"{to}","text":"{message}"}
        'token' => env('SMS_HTTP_TOKEN'),                 // sent as Authorization: Bearer <token> when set
        'country_code' => env('SMS_COUNTRY_CODE', '91'),
    ],

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'method' => env('WHATSAPP_HTTP_METHOD', 'post'),
        'url' => env('WHATSAPP_HTTP_URL'),
        'body' => env('WHATSAPP_HTTP_BODY'),
        'token' => env('WHATSAPP_HTTP_TOKEN'),
        'country_code' => env('WHATSAPP_COUNTRY_CODE', '91'),
    ],

];
