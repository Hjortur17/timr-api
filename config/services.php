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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/api/auth/callback/google'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'key_id' => env('APPLE_KEY_ID'),
        'team_id' => env('APPLE_TEAM_ID'),
        'private_key' => ($appleKey = env('APPLE_PRIVATE_KEY')) && is_file($appleKeyPath = base_path(ltrim($appleKey, '/')))
            ? file_get_contents($appleKeyPath)
            : $appleKey,
        'redirect' => env('APPLE_REDIRECT_URI', '/api/auth/callback/apple'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verifone Online Payments
    |--------------------------------------------------------------------------
    |
    | Subscription billing via Verifone's hosted Checkout (card capture during
    | trial) plus self-scheduled merchant-initiated charges. `enabled` is the
    | master switch: while false the VerifoneBillingProvider throws and the app
    | degrades to the graceful "billing_not_configured" seam. Base URLs are
    | resolved in code from `environment` + `region` — only credentials, the
    | acquiring identifiers and the JWKS URL live here.
    |
    */
    'verifone' => [
        'enabled' => env('VERIFONE_ENABLED', false),
        'environment' => env('VERIFONE_ENV', 'sandbox'), // sandbox | production
        'region' => env('VERIFONE_REGION', 'emea'),      // emea | us | nz
        // Basic auth (primary): the user-uid + API key issued in Verifone Central.
        'user_id' => env('VERIFONE_USER_ID'),
        'api_key' => env('VERIFONE_API_KEY'),
        // OAuth client-credentials (optional fallback; only for endpoints/contracts
        // that require a Bearer JWT instead of Basic auth).
        'client_id' => env('VERIFONE_CLIENT_ID'),
        'client_secret' => env('VERIFONE_CLIENT_SECRET'),
        'scope' => env('VERIFONE_OAUTH_SCOPE', ''),
        'entity_id' => env('VERIFONE_ENTITY_ID'),
        'payment_contract_id' => env('VERIFONE_PAYMENT_CONTRACT_ID'),
        'token_scope_id' => env('VERIFONE_TOKEN_SCOPE_ID'),
        'currency' => env('VERIFONE_CURRENCY', 'ISK'),
        'consent_text' => env('VERIFONE_CONSENT_TEXT', 'I authorise Timr to charge this card for my subscription on a recurring basis.'),
        'http_timeout' => (int) env('VERIFONE_HTTP_TIMEOUT', 15),
        'http_retries' => (int) env('VERIFONE_HTTP_RETRIES', 2),
        // Optional overrides; blank falls back to the per-environment default in VerifoneClient.
        'webhook_jwks_url' => env('VERIFONE_WEBHOOK_JWKS_URL'),
        // Where Verifone redirects the shopper back to after the hosted checkout.
        'checkout_return_url' => env('VERIFONE_CHECKOUT_RETURN_URL', env('FRONTEND_URL').'/dashboard/settings/billing?checkout=success'),
        'checkout_cancel_url' => env('VERIFONE_CHECKOUT_CANCEL_URL', env('FRONTEND_URL').'/dashboard/settings/billing?checkout=cancelled'),
    ],

];
