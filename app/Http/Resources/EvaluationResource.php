<?php

declare(strict_types=1);

namespace Phlag\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Phlag\Evaluations\EvaluationContext;
use Phlag\Evaluations\EvaluationResult;
use Phlag\Models\Evaluation;

/**
 * @mixin \Phlag\Models\Evaluation
 */
class EvaluationResource extends JsonResource
{
    /**
     * @param  array<string, string|array<int, string>>  $requestContext
     */
    public function __construct(
        Evaluation $resource,
        private readonly EvaluationContext $context,
        private readonly EvaluationResult $result,
        private readonly array $requestContext,
    ) {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Evaluation $evaluation */
        $evaluation = $this->resource;

        $result = [
            'variant' => $this->result->variant,
            'reason' => $this->result->reason,
            'rollout' => $this->result->rollout,
        ];

        if ($this->result->payload !== null) {
            $result['payload'] = $this->result->payload;
        }

        if ($this->result->bucket !== null) {
            $result['bucket'] = $this->result->bucket;
        }

        return [
            'id' => $evaluation->id,
            'project' => [
                'id' => $this->context->project->id,
                'key' => $this->context->project->key,
            ],
            'environment' => [
                'id' => $this->context->environment->id,
                'key' => $this->context->environment->key,
            ],
            'flag' => [
                'id' => $this->context->flag->id,
                'key' => $this->context->flag->key,
                'is_enabled' => $this->context->flag->is_enabled,
            ],
            'user' => [
                'identifier' => $this->context->userIdentifier,
            ],
            'context' => $this->requestContext,
            'result' => $result,
            'evaluated_at' => $evaluation->evaluated_at?->toISOString(),
        ];
    }
}
