<?php

namespace Phlag\Providers;

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Support\ServiceProvider;
use Phlag\Auth\Jwt\Configuration as JwtConfiguration;
use Phlag\Auth\Jwt\JwtTokenIssuer;
use Phlag\Auth\Jwt\JwtTokenVerifier;
use Phlag\Auth\Rbac\RoleRegistry;
use Phlag\Evaluations\Cache\FlagCacheRepository;
use Phlag\Http\Kernel as HttpKernel;
use Phlag\Models\Environment;
use Phlag\Models\Flag;
use Phlag\Models\Project;
use Phlag\Observers\EnvironmentObserver;
use Phlag\Observers\FlagObserver;
use Phlag\Observers\ProjectObserver;
use Phlag\Redis\RedisClient;
use Phlag\Support\Clock\Clock;
use Phlag\Support\Clock\SystemClock;

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

        $this->app->singleton(JwtConfiguration::class, function () {
            /** @var array<string, mixed> $config */
            $config = config('jwt', []);

            return JwtConfiguration::fromArray($config);
        });

        $this->app->singleton(Clock::class, static fn (): Clock => new SystemClock);

        $this->app->singleton(JwtTokenIssuer::class, function (): JwtTokenIssuer {
            return new JwtTokenIssuer(
                configuration: $this->app->make(JwtConfiguration::class),
                clock: $this->app->make(Clock::class)
            );
        });

        $this->app->singleton(RoleRegistry::class, static fn (): RoleRegistry => RoleRegistry::make());

        $this->app->singleton(JwtTokenVerifier::class, function (): JwtTokenVerifier {
            return new JwtTokenVerifier(
                configuration: $this->app->make(JwtConfiguration::class),
                clock: $this->app->make(Clock::class)
            );
        });

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

            $snapshotTtl = config('flag_cache.snapshot_ttl');
            $evaluationTtl = config('flag_cache.evaluation_ttl');
            $snapshotsEnabled = (bool) config('flag_cache.snapshots_enabled', true);
            $evaluationsEnabled = (bool) config('flag_cache.evaluations_enabled', true);

            return new FlagCacheRepository(
                redis: $client,
                snapshotTtlSeconds: is_numeric($snapshotTtl) ? (int) $snapshotTtl : null,
                evaluationTtlSeconds: is_numeric($evaluationTtl) ? (int) $evaluationTtl : null,
                snapshotsEnabled: $snapshotsEnabled,
                evaluationsEnabled: $evaluationsEnabled
            );
        });
    }
}
