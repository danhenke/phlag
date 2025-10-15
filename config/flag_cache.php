<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Redis Flag Cache TTLs
    |--------------------------------------------------------------------------
    |
    | These values control how long snapshot and evaluation entries remain in
    | Redis. Override them via environment variables to tune cache freshness
    | for different workloads.
    |
    */

    'snapshot_ttl' => (int) env('FLAG_CACHE_SNAPSHOT_TTL', 300),

    'evaluation_ttl' => (int) env('FLAG_CACHE_EVALUATION_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Redis Flag Cache Toggles
    |--------------------------------------------------------------------------
    |
    | Disable snapshot or evaluation caching locally by setting the associated
    | environment variable to false. This allows troubleshooting without
    | purging Redis or modifying production workloads.
    |
    */

    'snapshots_enabled' => filter_var(
        env('FLAG_CACHE_SNAPSHOTS_ENABLED', true),
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    ) ?? true,

    'evaluations_enabled' => filter_var(
        env('FLAG_CACHE_EVALUATIONS_ENABLED', true),
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    ) ?? true,
];
