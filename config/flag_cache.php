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
];
