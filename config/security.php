<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data-at-Rest Encryption Key & Rotation
    |--------------------------------------------------------------------------
    |
    | Dedicated key for encrypting database PII, secrets, and payloads.
    | Falls back to APP_KEY only when DATA_ENCRYPTION_KEY is not set.
    |
    */

    'cipher' => env('DATA_ENCRYPTION_CIPHER', 'aes-256-cbc'),

    'data_encryption_key' => env('DATA_ENCRYPTION_KEY'),

    'data_encryption_previous_keys' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DATA_ENCRYPTION_PREVIOUS_KEYS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Blind Index HMAC Key
    |--------------------------------------------------------------------------
    |
    | Dedicated key for deterministic HMAC-SHA256 blind indexes used to
    | query encrypted email, phone, and token columns without exposing plaintext.
    |
    */

    'blind_index_key' => env('BLIND_INDEX_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Transport Security (In-Transit)
    |--------------------------------------------------------------------------
    */

    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),

    'hsts_include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),

];
