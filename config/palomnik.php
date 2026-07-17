<?php

return [
    'admin' => [
        'name' => env('ADMIN_NAME', 'Главный администратор'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'maps' => [
        'style_url' => env('MAP_STYLE_URL'),
        'openmaptiles_tiles' => env('OPENMAPTILES_TILE_URL'),
        'glyphs_url' => env('MAP_GLYPHS_URL'),
        'raster_tiles' => env('MAP_RASTER_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'satellite_tiles' => env('MAP_SATELLITE_TILE_URL'),
        'historic_tiles' => env('MAP_HISTORIC_TILE_URL'),
        'attribution' => env('MAP_ATTRIBUTION', '© OpenStreetMap contributors'),
        'valhalla_url' => env('VALHALLA_URL', 'https://valhalla.openstreetmap.de'),
        'valhalla_timeout' => (int) env('VALHALLA_TIMEOUT', 20),
    ],

    'privacy' => [
        'policy_version' => env('PRIVACY_POLICY_VERSION', '1.0'),
    ],

    'firebase' => [
        'enabled' => env('FIREBASE_PUSH_ENABLED', false),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH'),
    ],
];
