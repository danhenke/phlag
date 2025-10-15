<?php

declare(strict_types=1);

namespace Phlag\Evaluations\Cache;

use Phlag\Models\Environment;
use Phlag\Models\Project;

final class FlagCacheInvalidator
{
    public function __construct(private readonly FlagCacheRepository $cacheRepository) {}

    public function invalidateEnvironment(Project $project, Environment $environment): void
    {
        $this->cacheRepository->forgetSnapshot($project->key, $environment->key);
        $this->cacheRepository->forgetEvaluations($project->key, $environment->key);
        $this->cacheRepository->publishInvalidation($project->key, $environment->key);
    }

    /**
     * @param  iterable<int, Environment>  $environments
     */
    public function invalidateEnvironments(Project $project, iterable $environments): void
    {
        foreach ($environments as $environment) {
            if (! $environment instanceof Environment) {
                continue;
            }

            $this->invalidateEnvironment($project, $environment);
        }
    }

    /**
     * @param  array<int, string>  $environmentKeys
     */
    public function invalidateEnvironmentKeys(Project $project, array $environmentKeys): void
    {
        foreach ($environmentKeys as $environmentKey) {
            $this->cacheRepository->forgetSnapshot($project->key, $environmentKey);
            $this->cacheRepository->forgetEvaluations($project->key, $environmentKey);
            $this->cacheRepository->publishInvalidation($project->key, $environmentKey);
        }
    }
}
