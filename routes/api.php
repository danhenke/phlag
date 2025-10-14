<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Phlag\Http\Controllers\HealthCheckController;
use Phlag\Http\Controllers\OpenApiController;
use Phlag\Http\Controllers\ProjectController;
use Phlag\Http\Controllers\ProjectEnvironmentController;
use Phlag\Http\Controllers\ProjectFlagController;
use Phlag\Http\Responses\ApiStub;

Route::get('/', HealthCheckController::class);

Route::prefix('v1')
    ->middleware('api')
    ->scopeBindings()
    ->group(function (): void {
        Route::post('/auth/token', fn () => ApiStub::notImplemented('Token issuance'));

        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('projects.environments', ProjectEnvironmentController::class);

        Route::apiResource('projects.flags', ProjectFlagController::class);

        Route::get('/evaluate', fn () => ApiStub::notImplemented('Flag evaluation'));

        Route::get('/docs/openapi.json', [OpenApiController::class, 'show'])
            ->name('docs.openapi');
    });
