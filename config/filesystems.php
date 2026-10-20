<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        /*
        | "local" holds private files (raw flyer uploads, licence documents) and
        | "public" holds what shoppers see (flyer pages, store logos). Both live
        | on this server's disk by default. Set MEDIA_STORAGE=cloud to keep them
        | in S3-compatible storage instead (Cloudflare R2, Supabase Storage,
        | AWS S3), which serverless hosts such as Vercel require. See
        | docs/VERCEL.md.
        */

        'local' => env('MEDIA_STORAGE', 'disk') === 'cloud' ? [
            'driver' => 's3',
            'key' => env('MEDIA_ACCESS_KEY_ID'),
            'secret' => env('MEDIA_SECRET_ACCESS_KEY'),
            'region' => env('MEDIA_REGION', 'auto'),
            'bucket' => env('MEDIA_PRIVATE_BUCKET'),
            'endpoint' => env('MEDIA_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('MEDIA_PATH_STYLE', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => env('MEDIA_STORAGE', 'disk') === 'cloud' ? [
            'driver' => 's3',
            'key' => env('MEDIA_ACCESS_KEY_ID'),
            'secret' => env('MEDIA_SECRET_ACCESS_KEY'),
            'region' => env('MEDIA_REGION', 'auto'),
            'bucket' => env('MEDIA_PUBLIC_BUCKET'),
            'endpoint' => env('MEDIA_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('MEDIA_PATH_STYLE', false),
            // Public base URL of the bucket, e.g. https://media.qdealme.com
            'url' => env('MEDIA_PUBLIC_URL'),
            'visibility' => 'public',
            'throw' => true,
            'report' => true,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            // Root-relative so images work on any host; Seo makes them absolute where needed.
            'url' => env('PUBLIC_DISK_URL', '/storage'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
