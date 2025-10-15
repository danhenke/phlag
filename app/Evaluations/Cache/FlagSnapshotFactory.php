<?php

declare(strict_types=1);

namespace Phlag\Evaluations\Cache;

use Illuminate\Support\Carbon;
use Phlag\Models\Environment;
use Phlag\Models\Flag;
use Phlag\Models\Project;

final class FlagSnapshotFactory
{
    /**
     * @param  iterable<int, Flag>  $flags
     * @return array{
     *     project: array<string, mixed>,
     *     environment: array<string, mixed>,
     *     flags: array<int, array<string, mixed>>,
     *     generated_at: string
     * }
     */
    public function make(
        Project $project,
        Environment $environment,
        iterable $flags
    ): array {
        $flagItems = [];

        foreach ($flags as $flag) {
            if (! $flag instanceof Flag) {
                continue;
            }

            $flagItems[] = [
                'id' => $flag->id,
                'project_id' => $flag->project_id,
                'key' => $flag->key,
                'name' => $flag->name,
                'description' => $flag->description,
                'is_enabled' => (bool) $flag->is_enabled,
                'variants' => $flag->variants,
                'rules' => $flag->rules,
                'updated_at' => $flag->updated_at?->toISOString(),
            ];
        }

        return [
            'project' => [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'description' => $project->description,
                'metadata' => $project->metadata,
            ],
            'environment' => [
                'id' => $environment->id,
                'project_id' => $environment->project_id,
                'key' => $environment->key,
                'name' => $environment->name,
                'description' => $environment->description,
                'is_default' => (bool) $environment->is_default,
            ],
            'flags' => $flagItems,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function hydrateProject(array $data): Project
    {
        return tap(new Project, static function (Project $project) use ($data): void {
            $project->forceFill([
                'id' => $data['id'] ?? null,
                'key' => $data['key'] ?? null,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $project->exists = true;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function hydrateEnvironment(array $data): Environment
    {
        return tap(new Environment, static function (Environment $environment) use ($data): void {
            $environment->forceFill([
                'id' => $data['id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'key' => $data['key'] ?? null,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            $environment->exists = true;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function hydrateFlag(array $data): Flag
    {
        return tap(new Flag, static function (Flag $flag) use ($data): void {
            $flag->forceFill([
                'id' => $data['id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'key' => $data['key'] ?? null,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'variants' => $data['variants'] ?? null,
                'rules' => $data['rules'] ?? null,
                'updated_at' => isset($data['updated_at']) && is_string($data['updated_at'])
                    ? Carbon::parse($data['updated_at'])
                    : null,
            ]);

            $flag->exists = true;
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    public function findFlag(array $snapshot, string $flagKey): ?array
    {
        $flags = $snapshot['flags'] ?? [];

        if (! is_iterable($flags)) {
            return null;
        }

        foreach ($flags as $flag) {
            if (! is_array($flag)) {
                continue;
            }

            if (($flag['key'] ?? null) === $flagKey) {
                /** @var array<string, mixed> $flag */
                return $flag;
            }
        }

        return null;
    }
}
