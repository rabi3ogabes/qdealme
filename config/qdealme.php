<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    */

    'brand' => [
        'name' => 'QDealMe',
        'domain' => env('QDEALME_DOMAIN', 'qdealme.com'),
        'support_email' => env('QDEALME_SUPPORT_EMAIL', 'hello@qdealme.com'),
        'legal_name' => env('QDEALME_LEGAL_NAME', 'QDealMe'),
        'social' => [
            'instagram' => env('QDEALME_INSTAGRAM', 'https://instagram.com/qdealme'),
            'x' => env('QDEALME_X', 'https://x.com/qdealme'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localisation
    |--------------------------------------------------------------------------
    |
    | Arabic is the first locale and is rendered right-to-left. Every public
    | URL carries the locale as its first segment.
    |
    */

    'locales' => [
        'ar' => ['name' => 'العربية', 'dir' => 'rtl', 'intl' => 'ar_QA', 'short' => 'AR'],
        'en' => ['name' => 'English', 'dir' => 'ltr', 'intl' => 'en_QA', 'short' => 'EN'],
    ],

    'default_locale' => 'ar',

    'default_country' => 'qatar',

    'default_city' => 'doha',

    'currency' => 'QAR',

    'timezone' => 'Asia/Qatar',

    /*
    |--------------------------------------------------------------------------
    | Listings
    |--------------------------------------------------------------------------
    */

    'flyers_per_page' => 16,

    'offers_per_page' => 24,

    'archive_per_page' => 24,

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Widths generated for every flyer page. The original is kept untouched.
    |
    */

    'derivative_widths' => [320, 640, 1024, 1600],

    'rasterize_dpi' => (int) env('QDEALME_RASTERIZE_DPI', 150),

    'pdftoppm_binary' => env('QDEALME_PDFTOPPM', 'pdftoppm'),

    'max_upload_mb' => (int) env('QDEALME_MAX_UPLOAD_MB', 80),

    /*
    |--------------------------------------------------------------------------
    | Admin security
    |--------------------------------------------------------------------------
    |
    | Staff must set up an authenticator app (with recovery codes) before
    | they can use the admin panel.
    |
    */

    'admin_require_mfa' => (bool) env('ADMIN_REQUIRE_MFA', true),
];
