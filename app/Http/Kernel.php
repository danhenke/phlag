<?php

declare(strict_types=1);

namespace Phlag\Http;

use Illuminate\Foundation\Http\Kernel as BaseKernel;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Phlag\Http\Middleware\RejectInvalidJson;

final class Kernel extends BaseKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        ValidatePostSize::class,
        TrimStrings::class,
        HandlePrecognitiveRequests::class,
        PreventRequestsDuringMaintenance::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'api' => [
            RejectInvalidJson::class,
            SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'bindings' => SubstituteBindings::class,
        'throttle' => ThrottleRequests::class,
    ];
}
