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

    'guest_portal' => [
        // Nunca usar APP_URL (API) como fallback del sitio público.
        'public_site_url' => rtrim(env('PUBLIC_SITE_URL', 'https://centrovacacionalcampoverde.com'), '/'),
        'otp_ttl_minutes' => (int) env('GUEST_PORTAL_OTP_TTL_MINUTES', 10),
        'otp_max_attempts' => (int) env('GUEST_PORTAL_OTP_MAX_ATTEMPTS', 5),
        'otp_request_limit' => (int) env('GUEST_PORTAL_OTP_REQUEST_LIMIT', 3),
        'otp_request_window_minutes' => (int) env('GUEST_PORTAL_OTP_REQUEST_WINDOW_MINUTES', 10),
        'session_ttl_hours' => (int) env('GUEST_PORTAL_SESSION_TTL_HOURS', 2),
    ],

];
