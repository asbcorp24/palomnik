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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'vkid' => [
        'client_id' => env('VKID_CLIENT_ID'),
        'client_secret' => env('VKID_CLIENT_SECRET'),
        'redirect' => env('VKID_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/auth/vk/callback'),
        'scopes' => array_values(array_filter(array_map('trim', explode(',', (string) env('VKID_SCOPES', 'email'))))),
        'pkce_ttl' => (int) env('VKID_PKCE_TTL', 10),
        'cache_store' => env('VKID_CACHE_STORE', 'file'),
        'cache_prefix' => env('VKID_CACHE_PREFIX', 'socialite:vkid:pkce:'),
        'api_version' => env('VKID_API_VERSION', '5.199'),
    ],

];
