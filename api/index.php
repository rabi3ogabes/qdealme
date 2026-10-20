<?php

/*
| Vercel entry point. Vercel's PHP runtime serves files from api/, so this
| hands every dynamic request to Laravel's normal front controller.
| Static files (built CSS/JS, fonts, images, admin assets) are served by
| Vercel directly from public/, see vercel.json.
|
| Serverless defaults: the code bundle is read-only and nothing persists
| between requests except /tmp, the database and cloud storage. Values set
| in the Vercel dashboard always win over these defaults.
*/

$defaults = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_CONFIG_CACHE' => '/tmp/qdealme/config.php',
    'APP_EVENTS_CACHE' => '/tmp/qdealme/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/qdealme/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/qdealme/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/qdealme/services.php',
    'VIEW_COMPILED_PATH' => '/tmp/qdealme/views',
    'LOG_CHANNEL' => 'stderr',
    'CACHE_STORE' => 'database',
    'SESSION_DRIVER' => 'database',
    'SESSION_SECURE_COOKIE' => 'true',
    'QUEUE_CONNECTION' => 'sync',
    'DB_CONNECTION' => 'pgsql',
    'DB_SSLMODE' => 'require',
    'MEDIA_STORAGE' => 'cloud',
    'QDEALME_IMAGE_PROCESSING' => 'basic',
    'TRUSTED_PROXIES' => '*',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false && ! isset($_ENV[$key]) && ! isset($_SERVER[$key])) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (! is_dir('/tmp/qdealme/views')) {
    @mkdir('/tmp/qdealme/views', 0775, true);
}

require __DIR__.'/../public/index.php';
