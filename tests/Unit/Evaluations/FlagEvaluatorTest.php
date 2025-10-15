<?php

declare(strict_types=1);

use Phlag\Evaluations\EvaluationContext;
use Phlag\Evaluations\EvaluationResult;
use Phlag\Evaluations\FlagEvaluator;
use Phlag\Models\Environment;
use Phlag\Models\Flag;
use Phlag\Models\Project;

/**
 * @return array{EvaluationContext, Flag, array<string, mixed>}
 */
function anonymousRolloutFixtures(array $attributes): array
{
    $project = new Project([
        'id' => 'proj-1',
        'key' => 'proj',
        'name' => 'Search',
    ]);

    $environment = new Environment([
        'id' => 'env-1',
        'project_id' => $project->id,
        'key' => 'production',
        'name' => 'Production',
        'is_default' => true,
    ]);

    $rule = [
        'match' => ['locale' => ['de-DE', 'en-US']],
        'variant' => 'beta',
        'rollout' => 100,
    ];

    $flag = new Flag([
        'id' => 'flag-1',
        'project_id' => $project->id,
        'key' => 'search-results',
        'name' => 'Search Results',
        'is_enabled' => true,
        'variants' => [
            ['key' => 'control', 'weight' => 100],
            ['key' => 'beta', 'weight' => 0],
        ],
        'rules' => [$rule],
    ]);

    $context = new EvaluationContext(
        project: $project,
        environment: $environment,
        flag: $flag,
        userIdentifier: null,
        attributes: $attributes,
    );

    return [$context, $flag, $rule];
}

function bucketForContext(FlagEvaluator $evaluator, EvaluationContext $context, string $variant): int
{
    static $method = null;

    if ($method === null) {
        $method = new ReflectionMethod(FlagEvaluator::class, 'bucketForRollout');
        $method->setAccessible(true);
    }

    /** @var int */
    $bucket = $method->invoke($evaluator, $context, $variant);

    return $bucket;
}

it('buckets attribute-only contexts for partial rollouts', function (): void {
    [$context, $flag, $rule] = anonymousRolloutFixtures(['locale' => ['de-DE']]);
    $evaluator = new FlagEvaluator;

    $bucket = bucketForContext($evaluator, $context, 'beta');

    $flag->rules = [array_merge($rule, ['rollout' => $bucket])];

    $result = $evaluator->evaluate($context);

    expect($result)->toBeInstanceOf(EvaluationResult::class)
        ->and($result->variant)->toBe('beta')
        ->and($result->reason)->toBe('matched_locale_rollout')
        ->and($result->rollout)->toBe($bucket)
        ->and($result->bucket)->toBe($bucket);
});

it('denies rollout variants when anonymous bucketing exceeds threshold', function (): void {
    [$context, $flag, $rule] = anonymousRolloutFixtures(['locale' => ['de-DE']]);
    $evaluator = new FlagEvaluator;

    $bucket = bucketForContext($evaluator, $context, 'beta');
    $rollout = max(0, $bucket - 1);

    $flag->rules = [array_merge($rule, ['rollout' => $rollout])];

    $result = $evaluator->evaluate($context);

    expect($result)->toBeInstanceOf(EvaluationResult::class)
        ->and($result->variant)->toBe('control')
        ->and($result->reason)->toBe('fallback_default')
        ->and($result->rollout)->toBe(0)
        ->and($result->bucket)->toBeNull();
});
