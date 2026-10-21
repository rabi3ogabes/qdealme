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

    // "auto" resizes pages and accepts PDFs when the server supports it.
    // "basic" stores uploaded images as they are and refuses PDFs; use it on
    // serverless hosts such as Vercel, which have no GD or Poppler.
    'image_processing' => env('QDEALME_IMAGE_PROCESSING', 'auto'),

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

    /*
    |--------------------------------------------------------------------------
    | Cron over HTTP
    |--------------------------------------------------------------------------
    |
    | Secret for /cron/flyers/roll, used by Vercel Cron instead of
    | `php artisan schedule:run`. Leave empty to disable the endpoint.
    |
    */

    'cron_secret' => env('CRON_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Facebook and Instagram (Meta's official Graph API)
    |--------------------------------------------------------------------------
    |
    | With a token from QDealMe's own Meta app, new flyer posts on the official
    | page of a store with an approved "social media" source are imported as
    | drafts automatically. Reading other businesses' pages needs Meta's
    | approval (Page Public Content Access for Facebook, Advanced Access for
    | Instagram business discovery); see docs/RETAILER-FEEDS.md. Leave the
    | token empty to switch this off.
    |
    */

    'meta' => [
        'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
        'version' => env('META_GRAPH_VERSION', 'v23.0'),
        'access_token' => env('META_ACCESS_TOKEN', ''),
        'app_secret' => env('META_APP_SECRET', ''),
        // QDealMe's own Instagram professional account, used to look up stores' public Instagram posts.
        'instagram_account_id' => env('META_INSTAGRAM_ACCOUNT_ID', ''),
        // Posts older than this are never imported.
        'max_post_age_days' => (int) env('META_MAX_POST_AGE_DAYS', 10),
    ],
];
