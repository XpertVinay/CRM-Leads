<?php

return [
    'default' => env('APP_LOCALE', 'en_IN'),
    'fallback' => env('APP_FALLBACK_LOCALE', 'hi_Latn'),
    'cookie_name' => 'crm_locale',
    'cookie_minutes' => 60 * 24 * 365,
    'supported' => [
        'en_IN' => [
            'label' => 'English (India)',
            'native' => 'English (India)',
            'description' => 'Standard Indian English interface',
            'html_lang' => 'en-IN',
            'carbon_locale' => 'en_IN',
            'short' => 'EN',
        ],
        'hi_Latn' => [
            'label' => 'Hinglish',
            'native' => 'Hinglish',
            'description' => 'Hindi + English in Roman script',
            'html_lang' => 'hi-Latn',
            'carbon_locale' => 'en_IN',
            'short' => 'HING',
        ],
        'hi' => [
            'label' => 'Hindi',
            'native' => 'हिन्दी',
            'description' => 'देवनागरी लिपि में हिन्दी',
            'html_lang' => 'hi',
            'carbon_locale' => 'hi',
            'short' => 'हिं',
        ],
    ],
];
