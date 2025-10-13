<?php

declare(strict_types=1);

namespace Phlag\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Phlag\Models\Environment;

/**
 * @mixin \Phlag\Models\Environment
 */
class EnvironmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Environment $environment */
        $environment = $this->resource;

        return [
            'id' => $environment->id,
            'key' => $environment->key,
            'name' => $environment->name,
            'description' => $environment->description,
            'is_default' => $environment->is_default,
            'metadata' => $environment->metadata,
            'created_at' => $environment->created_at?->toISOString(),
            'updated_at' => $environment->updated_at?->toISOString(),
        ];
    }
}
