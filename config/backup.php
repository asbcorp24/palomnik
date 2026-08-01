<?php

return [
    'enabled' => env('BACKUP_ENABLED', true),

    // Каталог обязан находиться вне public и storage/app/public.
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '01:30'),
    'keep_last' => max(1, (int) env('BACKUP_KEEP_LAST', 14)),
    'max_age_days' => max(1, (int) env('BACKUP_MAX_AGE_DAYS', 30)),
    'max_runtime_seconds' => max(60, (int) env('BACKUP_MAX_RUNTIME_SECONDS', 3600)),
    'minimum_free_space_mb' => max(128, (int) env('BACKUP_MIN_FREE_SPACE_MB', 2048)),
    'deployment_max_age_minutes' => max(5, (int) env('DEPLOY_BACKUP_MAX_AGE_MINUTES', 60)),

    'database' => [
        'enabled' => env('BACKUP_DATABASE_ENABLED', true),
        'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
        'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
        'additional_options' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MYSQLDUMP_ADDITIONAL_OPTIONS', ''))
        ))),
    ],

    'public_files' => [
        'enabled' => env('BACKUP_PUBLIC_FILES_ENABLED', true),
        'path' => storage_path('app/public'),
        'tar_binary' => env('TAR_BINARY', 'tar'),
    ],

    'deployments_path' => env('DEPLOYMENTS_PATH', storage_path('app/deployments')),
];
