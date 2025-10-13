<?php

declare(strict_types=1);

namespace Phlag\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define the routes for the application.
     */
    public function boot(): void
    {
        parent::boot();

        Route::middleware('api')
            ->group(base_path('routes/api.php'));
    }
}
