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
];
