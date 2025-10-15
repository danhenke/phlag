<?php

declare(strict_types=1);

namespace Phlag\Observers;

use Phlag\Evaluations\Cache\FlagCacheInvalidator;
use Phlag\Models\Environment;
use Phlag\Models\Project;

final class EnvironmentObserver
{
    public function __construct(private readonly FlagCacheInvalidator $invalidator) {}

    public function saved(Environment $environment): void
    {
        $this->invalidate($environment);
    }

    public function deleted(Environment $environment): void
    {
        $this->invalidate($environment);
    }

    private function invalidate(Environment $environment): void
    {
        $project = $environment->project;

        if (! $project instanceof Project) {
            $project = Project::query()->find($environment->project_id);
        }

        if ($project === null) {
            return;
        }

        $this->invalidator->invalidateEnvironment($project, $environment);
    }
}
