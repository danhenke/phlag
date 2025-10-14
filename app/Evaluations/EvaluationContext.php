<?php

declare(strict_types=1);

namespace Phlag\Evaluations;

use Phlag\Models\Environment;
use Phlag\Models\Flag;
use Phlag\Models\Project;

/**
 * @phpstan-type AttributeMap array<string, array<int, string>>
 */
final class EvaluationContext
{
    /**
     * @param  AttributeMap  $attributes
     */
    public function __construct(
        public readonly Project $project,
        public readonly Environment $environment,
        public readonly Flag $flag,
        public readonly ?string $userIdentifier,
        /** @var AttributeMap */
        public readonly array $attributes,
    ) {}

    /**
     * Prepare context attributes for persistence or API output.
     *
     * @return array<string, string|array<int, string>>
     */
    public function denormalizedAttributes(): array
    {
        $denormalized = [];

        foreach ($this->attributes as $key => $values) {
            $values = array_values(array_filter($values, static fn ($value): bool => $value !== ''));

            if ($values === []) {
                continue;
            }

            $denormalized[$key] = count($values) === 1 ? $values[0] : $values;
        }

        return $denormalized;
    }
}
