<?php

declare(strict_types=1);

namespace Phlag\Observers;

use Phlag\Evaluations\Cache\FlagCacheInvalidator;
use Phlag\Models\Environment;
use Phlag\Models\Project;

final class ProjectObserver
{
    public function __construct(private readonly FlagCacheInvalidator $invalidator) {}

    public function saved(Project $project): void
    {
        $this->invalidate($project);
    }

    public function deleted(Project $project): void
    {
        $this->invalidate($project);
    }

    private function invalidate(Project $project): void
    {
        /** @var array<int, string> $environmentKeys */
        $environmentKeys = Environment::query()
            ->where('project_id', $project->id)
            ->pluck('key')
            ->all();

        if ($environmentKeys === []) {
            return;
        }

        $this->invalidator->invalidateEnvironmentKeys($project, $environmentKeys);
    }
}
