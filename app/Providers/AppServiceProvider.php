<?php

namespace Phlag\Providers;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Support\ServiceProvider;
use Phlag\Evaluations\Cache\FlagCacheRepository;
use Phlag\Http\Kernel as HttpKernel;
use Phlag\Models\Environment;
use Phlag\Models\Flag;
use Phlag\Models\Project;
use Phlag\Observers\EnvironmentObserver;
use Phlag\Observers\FlagObserver;
use Phlag\Observers\ProjectObserver;
use Phlag\Redis\RedisClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Flag::observe(FlagObserver::class);
        Environment::observe(EnvironmentObserver::class);
        Project::observe(ProjectObserver::class);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! $this->app->bound(HttpKernelContract::class)) {
            $this->app->singleton(HttpKernelContract::class, HttpKernel::class);
        }

        $this->app->singleton(FlagCacheRepository::class, function () {
            $config = config('database.redis.cache');
            $client = null;

            if (is_array($config) && $config !== []) {
                /** @var array<string, mixed> $cacheConfig */
                $cacheConfig = $config;

                try {
                    $client = RedisClient::fromConfig($cacheConfig);
                } catch (\Throwable) {
                    $client = null;
                }
            }

            return new FlagCacheRepository($client);
        });
    }
}
