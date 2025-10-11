<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'useDefaultProvider' => true,

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'postgres'),
            'port' => (int) env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'phlag'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'postgres'),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'public'),
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => (bool) env('DB_FOREIGN_KEYS', true),
        ],
    ],

    'migrations' => 'migrations',
];
