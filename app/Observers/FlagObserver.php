<?php

declare(strict_types=1);

namespace Phlag\Observers;

use Phlag\Evaluations\Cache\FlagCacheInvalidator;
use Phlag\Models\Environment;
use Phlag\Models\Flag;
use Phlag\Models\Project;

final class FlagObserver
{
    public function __construct(private readonly FlagCacheInvalidator $invalidator) {}

    public function saved(Flag $flag): void
    {
        $this->invalidate($flag);
    }

    public function deleted(Flag $flag): void
    {
        $this->invalidate($flag);
    }

    private function invalidate(Flag $flag): void
    {
        $project = Project::query()->find($flag->project_id);

        if ($project === null) {
            return;
        }

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
