<?php

declare(strict_types=1);

namespace Phlag\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Phlag\Models\Project;

/**
 * @mixin \Phlag\Models\Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        $environments = $project->relationLoaded('environments')
            ? $project->environments
            : [];

        return [
            'id' => $project->id,
            'key' => $project->key,
            'name' => $project->name,
            'description' => $project->description,
            'metadata' => $project->metadata,
            'created_at' => $project->created_at?->toISOString(),
            'updated_at' => $project->updated_at?->toISOString(),
            'environments' => EnvironmentResource::collection($environments),
        ];
    }
}
