<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Signing Keys
    |--------------------------------------------------------------------------
    |
    | Configure the signing material used for issuing and validating JSON Web
    | Tokens. Provide RSA key pairs via JWT_KEY_ID, JWT_PRIVATE_KEY, and
    | JWT_PUBLIC_KEY for production. When RSA keys are absent the application
    | falls back to HS256 using the JWT_SECRET for local development.
    |
    | Optionally supply the previous public key to support phased rotation.
    |
    */

    'keys' => [
        'active' => [
            'id' => env('JWT_KEY_ID'),
            'private_key' => env('JWT_PRIVATE_KEY'),
            'public_key' => env('JWT_PUBLIC_KEY'),
        ],
        'previous' => [
            'id' => env('JWT_PREVIOUS_KEY_ID'),
            'public_key' => env('JWT_PREVIOUS_PUBLIC_KEY'),
        ],
        'secret' => env('JWT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Time Configuration
    |--------------------------------------------------------------------------
    |
    | Tokens default to a one-hour lifetime and tolerate a minute of clock
    | skew when being validated. Override these values per environment to
    | tighten expiry or support distributed systems with skewed clocks.
    |
    */

    'ttl' => (int) env('JWT_TTL', 3600),
    'clock_skew' => (int) env('JWT_CLOCK_SKEW', 60),

    /*
    |--------------------------------------------------------------------------
    | Required Claims
    |--------------------------------------------------------------------------
    |
    | Every verified token must provide these claims. Adjust the list if the
    | platform introduces additional mandatory metadata.
    |
    */

    'required_claims' => [
        'sub',
        'iat',
        'exp',
    ],
];
