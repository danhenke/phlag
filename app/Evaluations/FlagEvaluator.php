<?php

declare(strict_types=1);

namespace Phlag\Evaluations;

use Phlag\Models\Flag;

final class FlagEvaluator
{
    public function evaluate(EvaluationContext $context): EvaluationResult
    {
        $flag = $context->flag;

        if (! $flag->is_enabled) {
            return $this->flagDisabled($context);
        }

        $rules = $flag->rules;

        if (! is_array($rules)) {
            $rules = [];
        }

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            /** @var array<string, mixed> $rule */
            $outcome = $this->evaluateRule($context, $rule);

            if ($outcome !== null) {
                return $outcome;
            }
        }

        return $this->defaultFallback($context);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function evaluateRule(EvaluationContext $context, array $rule): ?EvaluationResult
    {
        $matchDefinition = $rule['match'] ?? null;

        if (! is_array($matchDefinition) || $matchDefinition === []) {
            return null;
        }

        $normalizedMatch = [];

        foreach ($matchDefinition as $attribute => $values) {
            if (! is_string($attribute) || ! is_array($values)) {
                continue;
            }

            $normalizedValues = array_values(array_filter(
                array_map(
                    static fn ($value): ?string => is_scalar($value) ? (string) $value : null,
                    $values
                ),
                static fn (?string $value): bool => $value !== null && $value !== ''
            ));

            if ($normalizedValues === []) {
                continue;
            }

            $normalizedMatch[$attribute] = $normalizedValues;
        }

        if ($normalizedMatch === []) {
            return null;
        }

        /** @var array<string, array<int, string>> $match */
        $match = $normalizedMatch;

        if (! $this->matchesRule($match, $context->attributes)) {
            return null;
        }

        $variantKey = $this->stringValue($rule['variant'] ?? null);

        if ($variantKey === null) {
            return null;
        }

        /** @var array<string, mixed>|null $definition */
        $definition = $this->findVariantDefinition($context->flag, $variantKey);

        if ($definition === null) {
            return null;
        }

        $rollout = $this->normalizeRollout($rule['rollout'] ?? 100);

        if ($rollout === 0) {
            return null;
        }

        $primaryKey = $this->primaryMatchKey($match);

        if ($rollout >= 100) {
            $reason = sprintf('matched_%s', $primaryKey);

            return $this->makeResult($definition, $variantKey, $reason, $rollout);
        }

        // Ensure partial rollouts still respect bucketing for anonymous contexts.
        $bucket = $this->bucketForRollout($context, $variantKey);

        if ($bucket <= $rollout) {
            $reason = sprintf('matched_%s_rollout', $primaryKey);

            return $this->makeResult($definition, $variantKey, $reason, $rollout, $bucket);
        }

        return null;
    }

    /**
     * @param  array<string, array<int, string>>  $match
     * @param  array<string, array<int, string>>  $attributes
     */
    private function matchesRule(array $match, array $attributes): bool
    {
        foreach ($match as $key => $expectedValues) {
            if (! is_array($expectedValues) || $expectedValues === []) {
                return false;
            }

            $expected = array_map(static fn ($value): string => (string) $value, $expectedValues);
            $actual = $attributes[$key] ?? [];

            if ($actual === []) {
                return false;
            }

            if (count(array_intersect($expected, $actual)) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $variantDefinition
     */
    private function makeResult(array $variantDefinition, string $variantKey, string $reason, int $rollout, ?int $bucket = null): EvaluationResult
    {
        $payloadValue = $variantDefinition['payload'] ?? null;

        /** @var array<string, mixed>|null $payload */
        $payload = is_array($payloadValue) ? $payloadValue : null;

        return new EvaluationResult(
            variant: $variantKey,
            reason: $reason,
            rollout: $rollout,
            payload: $payload,
            bucket: $bucket
        );
    }

    private function flagDisabled(EvaluationContext $context): EvaluationResult
    {
        $variant = $this->firstVariant($context->flag);

        if ($variant === null) {
            return new EvaluationResult(
                variant: null,
                reason: 'flag_disabled',
                rollout: 0
            );
        }

        $variantKey = $this->stringValue($variant['key'] ?? null);

        if ($variantKey === null) {
            return new EvaluationResult(
                variant: null,
                reason: 'flag_disabled',
                rollout: 0
            );
        }

        return $this->makeResult($variant, $variantKey, 'flag_disabled', 0);
    }

    private function defaultFallback(EvaluationContext $context): EvaluationResult
    {
        $variantKey = $this->selectWeightedVariant($context);

        if ($variantKey === null) {
            return new EvaluationResult(
                variant: null,
                reason: 'fallback_default',
                rollout: 0
            );
        }

        $definition = $this->findVariantDefinition($context->flag, $variantKey);

        if ($definition === null) {
            return new EvaluationResult(
                variant: null,
                reason: 'fallback_default',
                rollout: 0
            );
        }

        return $this->makeResult($definition, $variantKey, 'fallback_default', 0);
    }

    private function selectWeightedVariant(EvaluationContext $context): ?string
    {
        $variants = $context->flag->variants ?? [];

        if (! is_array($variants) || $variants === []) {
            return null;
        }

        /** @var array<int, array{key: string, weight: int}> $weights */
        $weights = [];
        $totalWeight = 0;

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $key = $this->stringValue($variant['key'] ?? null);

            if ($key === null) {
                continue;
            }

            $weightValue = $variant['weight'] ?? 0;
            $weight = is_numeric($weightValue) ? (int) $weightValue : 0;

            if ($weight <= 0) {
                continue;
            }

            $weights[] = [
                'key' => $key,
                'weight' => $weight,
            ];
            $totalWeight += $weight;
        }

        if ($weights === []) {
            $first = $this->firstVariant($context->flag);

            return is_string($first['key'] ?? null) ? $first['key'] : null;
        }

        $scaled = $this->hashToScale($context, 'default', $totalWeight);
        $running = 0;

        foreach ($weights as $entry) {
            $running += $entry['weight'];

            if ($scaled <= $running) {
                return $entry['key'];
            }
        }

        $last = end($weights);

        return is_array($last) && is_string($last['key'] ?? null) ? $last['key'] : null;
    }

    /**
     * @param  array<string, array<int, string>>|null  $match
     */
    private function primaryMatchKey(?array $match): string
    {
        $key = $match !== null ? array_key_first($match) : null;

        if (! is_string($key) || $key === '') {
            return 'rule';
        }

        return $key;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstVariant(Flag $flag): ?array
    {
        $variants = $flag->variants ?? [];

        if (! is_array($variants) || $variants === []) {
            return null;
        }

        $candidate = $variants[0];

        return is_array($candidate) ? $candidate : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findVariantDefinition(Flag $flag, string $variantKey): ?array
    {
        $variants = $flag->variants ?? [];

        if (! is_array($variants)) {
            return null;
        }

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            if (($variant['key'] ?? null) === $variantKey) {
                return $variant;
            }
        }

        return null;
    }

    private function normalizeRollout(mixed $rollout): int
    {
        if (! is_numeric($rollout)) {
            return 100;
        }

        $normalized = (int) $rollout;

        return max(0, min(100, $normalized));
    }

    private function bucketForRollout(EvaluationContext $context, string $salt): int
    {
        return $this->hashToScale($context, $salt, 100);
    }

    private function hashToScale(EvaluationContext $context, string $salt, int $max): int
    {
        $hash = $this->hash($context, $salt);

        return (int) (($hash % $max) + 1);
    }

    private function hash(EvaluationContext $context, string $salt): int
    {
        $parts = [
            (string) $context->project->key,
            (string) $context->environment->key,
            (string) $context->flag->key,
            $salt,
        ];

        if ($context->userIdentifier !== null && $context->userIdentifier !== '') {
            $parts[] = $context->userIdentifier;
        } else {
            $parts[] = json_encode($context->attributes, JSON_THROW_ON_ERROR);
        }

        $seed = implode('|', $parts);
        $hash = crc32($seed);

        return (int) sprintf('%u', $hash);
    }
}
