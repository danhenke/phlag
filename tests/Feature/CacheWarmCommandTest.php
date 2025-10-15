<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Phlag\Evaluations\Cache\FlagCacheRepository;
use Phlag\Evaluations\Cache\FlagSignatureHasher;
use Phlag\Models\Environment;
use Phlag\Models\Flag;
use Phlag\Models\Project;

beforeEach(function (): void {
    $this->artisan('migrate:fresh')->assertExitCode(0);
});

it('warms snapshot and evaluation caches for a project environment', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'search',
        'name' => 'Search Service',
    ]);

    $environment = Environment::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $project->id,
        'key' => 'production',
        'name' => 'Production',
        'is_default' => true,
    ]);

    $flag = Flag::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $project->id,
        'key' => 'search-results',
        'name' => 'Search Results v2',
        'is_enabled' => true,
        'variants' => [
            ['key' => 'control', 'weight' => 100],
        ],
        'rules' => [],
    ]);

    $this->getJson(sprintf(
        '/v1/evaluate?project=%s&env=%s&flag=%s&locale=en-US',
        $project->key,
        $environment->key,
        $flag->key
    ))->assertOk();

    /** @var FlagCacheRepository $repository */
    $repository = app(FlagCacheRepository::class);
    $repository->forgetSnapshot($project->key, $environment->key);
    $repository->forgetEvaluations($project->key, $environment->key);

    expect($repository->getSnapshot($project->key, $environment->key))->toBeNull();

    /** @var FlagSignatureHasher $signatureHasher */
    $signatureHasher = app(FlagSignatureHasher::class);
    $flagSignature = $signatureHasher->hash($flag);

    $evaluationAttributes = ['locale' => ['en-US']];

    expect($repository->getEvaluation(
        $project->key,
        $environment->key,
        $flag->key,
        null,
        $evaluationAttributes,
        $flagSignature
    ))->toBeNull();

    $this->artisan('cache:warm', [
        'project' => $project->key,
        'environment' => $environment->key,
    ])->assertExitCode(0);

    $snapshot = $repository->getSnapshot($project->key, $environment->key);

    expect($snapshot)
        ->not->toBeNull()
        ->and($snapshot['project']['key'] ?? null)->toBe($project->key)
        ->and($snapshot['environment']['key'] ?? null)->toBe($environment->key);

    $cachedEvaluation = $repository->getEvaluation(
        $project->key,
        $environment->key,
        $flag->key,
        null,
        $evaluationAttributes,
        $flagSignature
    );

    expect($cachedEvaluation)
        ->not->toBeNull()
        ->and($cachedEvaluation['variant'] ?? null)->toBe('control')
        ->and($cachedEvaluation['reason'] ?? null)->toBe('fallback_default')
        ->and($cachedEvaluation['rollout'] ?? null)->toBe(0);

    $evaluationKey = (fn (
        string $projectKey,
        string $environmentKey,
        string $flagKey,
        ?string $userIdentifier,
        array $attributes,
        ?string $signature = null
    ) => $this->evaluationKey($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $signature))
        ->call($repository, $project->key, $environment->key, $flag->key, null, $evaluationAttributes, $flagSignature);

    $evaluationStore = (fn () => $this->arrayEvaluations)->call($repository);
    $initialEntry = $evaluationStore[$evaluationKey] ?? null;

    expect($initialEntry)->not->toBeNull();

    sleep(1);

    $this->getJson(sprintf(
        '/v1/evaluate?project=%s&env=%s&flag=%s&locale=en-US',
        $project->key,
        $environment->key,
        $flag->key
    ))
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('data.flag.key', $flag->key)
            ->where('data.result.variant', 'control')
            ->where('data.result.reason', 'fallback_default')
        );

    $postEvaluationStore = (fn () => $this->arrayEvaluations)->call($repository);
    $postEntry = $postEvaluationStore[$evaluationKey] ?? null;

    expect($postEntry)
        ->not->toBeNull()
        ->and($postEntry['expires_at'] ?? null)->toBe($initialEntry['expires_at']);

    app()->forgetInstance(FlagCacheRepository::class);
});
