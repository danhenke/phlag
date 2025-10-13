<?php

namespace Phlag\Providers;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Support\ServiceProvider;
use Phlag\Http\Kernel as HttpKernel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! $this->app->bound(HttpKernelContract::class)) {
            $this->app->singleton(HttpKernelContract::class, HttpKernel::class);
        }
    }
}
