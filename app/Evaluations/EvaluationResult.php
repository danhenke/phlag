<?php

declare(strict_types=1);

namespace Phlag\Evaluations;

final class EvaluationResult
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public readonly ?string $variant,
        public readonly string $reason,
        public readonly int $rollout,
        public readonly ?array $payload = null,
        public readonly ?int $bucket = null,
    ) {}

    /**
     * Shape payload for database persistence.
     *
     * @return array<string, mixed>
     */
    public function payloadForStorage(): array
    {
        $payload = [
            'variant' => $this->variant,
            'rollout' => $this->rollout,
        ];

        if ($this->payload !== null) {
            $payload['payload'] = $this->payload;
        }

        if ($this->bucket !== null) {
            $payload['bucket'] = $this->bucket;
        }

        return $payload;
    }
}
