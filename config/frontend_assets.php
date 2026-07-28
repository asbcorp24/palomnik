<?php

return [
    'disk' => env('FRONTEND_ASSETS_DISK', 'local'),
    'directory' => trim(env('FRONTEND_ASSETS_DIRECTORY', 'frontend-assets'), '/'),
    'timeout' => (int) env('FRONTEND_ASSETS_TIMEOUT', 45),

    /*
     * The browser never receives these remote addresses. They are used only by
     * the server to fill its local cache when an asset is requested for the
     * first time or when `php artisan frontend-assets:cache --refresh` runs.
     */
    'assets' => [
        'bootstrap/bootstrap.min.css' => [
            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            'content_type' => 'text/css; charset=UTF-8',
        ],
        'bootstrap/bootstrap.bundle.min.js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            'content_type' => 'application/javascript; charset=UTF-8',
        ],
        'bootstrap-icons/bootstrap-icons.min.css' => [
            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
            'content_type' => 'text/css; charset=UTF-8',
        ],
        'bootstrap-icons/fonts/bootstrap-icons.woff2' => [
            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff2?dd67030699838ea613ee6dbda90effa6',
            'content_type' => 'font/woff2',
        ],
        'bootstrap-icons/fonts/bootstrap-icons.woff' => [
            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/bootstrap-icons.woff?dd67030699838ea613ee6dbda90effa6',
            'content_type' => 'font/woff',
        ],
        'maplibre/maplibre-gl.css' => [
            'url' => 'https://unpkg.com/maplibre-gl@5.24.0/dist/maplibre-gl.css',
            'content_type' => 'text/css; charset=UTF-8',
        ],
        'maplibre/maplibre-gl.js' => [
            'url' => 'https://unpkg.com/maplibre-gl@5.24.0/dist/maplibre-gl.js',
            'content_type' => 'application/javascript; charset=UTF-8',
        ],
        'html5-qrcode/html5-qrcode.min.js' => [
            'url' => 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
            'content_type' => 'application/javascript; charset=UTF-8',
        ],
        'qrcodejs/qrcode.min.js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js',
            'content_type' => 'application/javascript; charset=UTF-8',
        ],
    ],

    'replacements' => [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' => 'bootstrap/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js' => 'bootstrap/bootstrap.bundle.min.js',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' => 'bootstrap-icons/bootstrap-icons.min.css',
        'https://unpkg.com/maplibre-gl@5/dist/maplibre-gl.css' => 'maplibre/maplibre-gl.css',
        'https://unpkg.com/maplibre-gl@5/dist/maplibre-gl.js' => 'maplibre/maplibre-gl.js',
        'https://unpkg.com/maplibre-gl@5.24.0/dist/maplibre-gl.css' => 'maplibre/maplibre-gl.css',
        'https://unpkg.com/maplibre-gl@5.24.0/dist/maplibre-gl.js' => 'maplibre/maplibre-gl.js',
        'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js' => 'html5-qrcode/html5-qrcode.min.js',
        'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js' => 'qrcodejs/qrcode.min.js',
    ],
];
