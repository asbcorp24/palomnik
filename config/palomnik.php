<?php

return [
    'admin' => [
        'name' => env('ADMIN_NAME', 'Главный администратор'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'maps' => [
        'yandex_key' => env('YANDEX_MAPS_API_KEY'),
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
