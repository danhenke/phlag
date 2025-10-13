<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Phlag\Http\Controllers\HealthCheckController;
use Phlag\Http\Responses\ApiStub;

Route::get('/', HealthCheckController::class);

Route::prefix('v1')
    ->middleware('api')
    ->group(function (): void {
        Route::post('/auth/token', fn () => ApiStub::notImplemented('Token issuance'));

        Route::get('/projects', fn () => ApiStub::notImplemented('Project listing'));
        Route::post('/projects', fn () => ApiStub::notImplemented('Project creation'));

        Route::get('/projects/{project}/flags', fn () => ApiStub::notImplemented('Flag listing'));
        Route::post('/projects/{project}/flags', fn () => ApiStub::notImplemented('Flag creation'));
        Route::patch('/projects/{project}/flags/{key}', fn () => ApiStub::notImplemented('Flag update'));
        Route::delete('/projects/{project}/flags/{key}', fn () => ApiStub::notImplemented('Flag deletion'));

        Route::get('/evaluate', fn () => ApiStub::notImplemented('Flag evaluation'));

        Route::get('/docs/openapi.json', fn () => ApiStub::notImplemented('OpenAPI specification'));
    });
