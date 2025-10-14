<?php

declare(strict_types=1);

namespace Phlag\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Phlag\Models\Flag;

/**
 * @mixin \Phlag\Models\Flag
 */
class FlagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Flag $flag */
        $flag = $this->resource;

        return [
            'id' => $flag->id,
            'project_id' => $flag->project_id,
            'key' => $flag->key,
            'name' => $flag->name,
            'description' => $flag->description,
            'is_enabled' => $flag->is_enabled,
            'variants' => $flag->variants,
            'rules' => $flag->rules,
            'created_at' => $flag->created_at?->toISOString(),
            'updated_at' => $flag->updated_at?->toISOString(),
        ];
    }
}
