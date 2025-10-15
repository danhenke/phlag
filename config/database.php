<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'useDefaultProvider' => true,

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT') !== null ? (int) env('DB_PORT') : null,
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => env('DB_PREFIX', ''),
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

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'prefix' => env('REDIS_PREFIX', 'phlag:'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', 'redis'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT') !== null ? (int) env('REDIS_PORT') : 6379,
            'database' => env('REDIS_DB') !== null ? (int) env('REDIS_DB') : 0,
        ],
        'cache' => [
            'url' => env('REDIS_CACHE_URL'),
            'host' => env('REDIS_CACHE_HOST', env('REDIS_HOST', 'redis')),
            'password' => env('REDIS_CACHE_PASSWORD', env('REDIS_PASSWORD')),
            'port' => env('REDIS_CACHE_PORT') !== null
                ? (int) env('REDIS_CACHE_PORT')
                : (env('REDIS_PORT') !== null ? (int) env('REDIS_PORT') : 6379),
            'database' => env('REDIS_CACHE_DB') !== null ? (int) env('REDIS_CACHE_DB') : 1,
        ],
    ],

    'migrations' => 'migrations',
];
