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
Route::get(
    '/postman/FeatureFlagService.postman_collection.json',
    PostmanCollectionController::class
)->name('docs.postman');

Route::prefix('v1')
    ->middleware('api')
    ->scopeBindings()
    ->group(function (): void {
        Route::post('/auth/token', [AuthTokenController::class, 'store'])
            ->name('auth.token');

        Route::middleware('auth.jwt')->group(function (): void {
            Route::apiResource('projects', ProjectController::class);
            Route::apiResource('projects.environments', ProjectEnvironmentController::class);

            Route::apiResource('projects.flags', ProjectFlagController::class);

            Route::get('/evaluate', EvaluateFlagController::class)
                ->name('flags.evaluate');
        });

        Route::get('/openapi.json', [OpenApiController::class, 'show'])
            ->name('docs.openapi');
    });
