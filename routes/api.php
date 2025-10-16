<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Phlag\Http\Controllers\AuthTokenController;
use Phlag\Http\Controllers\EvaluateFlagController;
use Phlag\Http\Controllers\HealthCheckController;
use Phlag\Http\Controllers\OpenApiController;
use Phlag\Http\Controllers\PostmanCollectionController;
use Phlag\Http\Controllers\ProjectController;
use Phlag\Http\Controllers\ProjectEnvironmentController;
use Phlag\Http\Controllers\ProjectFlagController;

Route::get('/', HealthCheckController::class);
Route::get('/docs', [OpenApiController::class, 'ui'])->name('docs.ui');
Route::prefix('v1')
    ->middleware('api')
    ->scopeBindings()
    ->group(function (): void {
        Route::post('/auth/token', [AuthTokenController::class, 'store'])
            ->name('auth.token');

        Route::middleware('auth.jwt')->group(function (): void {
            Route::apiResource('projects', ProjectController::class)
                ->only(['index', 'show'])
                ->middleware('scopes:projects.read');

            Route::apiResource('projects', ProjectController::class)
                ->only(['store', 'update', 'destroy'])
                ->middleware('scopes:projects.manage');

            Route::apiResource('projects.environments', ProjectEnvironmentController::class)
                ->only(['index', 'show'])
                ->middleware('scopes:environments.read');

            Route::apiResource('projects.environments', ProjectEnvironmentController::class)
                ->only(['store', 'update', 'destroy'])
                ->middleware('scopes:environments.manage');

            Route::apiResource('projects.flags', ProjectFlagController::class)
                ->only(['index', 'show'])
                ->middleware('scopes:flags.read');

            Route::apiResource('projects.flags', ProjectFlagController::class)
                ->only(['store', 'update', 'destroy'])
                ->middleware('scopes:flags.manage');

            Route::get('/evaluate', EvaluateFlagController::class)
                ->middleware('scopes:flags.evaluate')
                ->name('flags.evaluate');
        });

        Route::get('/openapi.json', [OpenApiController::class, 'show'])
            ->name('docs.openapi');

        Route::get('/postman.json', PostmanCollectionController::class)
            ->name('docs.postman');
    });
