<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Phlag\Commands\Cache\WarmCommand;
use Phlag\Evaluations\Cache\FlagCacheRepository;
use Phlag\Evaluations\Cache\FlagSignatureHasher;
use Phlag\Evaluations\Cache\FlagSnapshotFactory;
use Phlag\Evaluations\FlagEvaluator;
use Phlag\Models\Environment;
use Phlag\Models\Evaluation;
use Phlag\Models\Flag;
use Phlag\Models\Project;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function (): void {
    app()->forgetInstance(FlagCacheRepository::class);
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

    /** @var FlagCacheRepository $initialRepository */
    $initialRepository = app(FlagCacheRepository::class);
    app()->instance(FlagCacheRepository::class, $initialRepository);
    $initialRepository->forgetSnapshot($project->key, $environment->key);
    $initialRepository->forgetEvaluations($project->key, $environment->key);

    expect($initialRepository->getSnapshot($project->key, $environment->key))->toBeNull();

    /** @var FlagSignatureHasher $signatureHasher */
    $signatureHasher = app(FlagSignatureHasher::class);
    $flagSignature = $signatureHasher->hash($flag);

    $evaluationAttributes = ['locale' => ['en-US']];

    expect($initialRepository->getEvaluation(
        $project->key,
        $environment->key,
        $flag->key,
        null,
        $evaluationAttributes,
        $flagSignature
    ))->toBeNull();

    $snapshotFactory = app(FlagSnapshotFactory::class);
    $flagEvaluator = app(FlagEvaluator::class);
    $signatureHasher = app(FlagSignatureHasher::class);

    $command = new WarmCommand($initialRepository, $snapshotFactory, $flagEvaluator, $signatureHasher);
    $command->setLaravel(app());
    $command->run(new ArrayInput([
        'project' => $project->key,
        'environment' => $environment->key,
    ]), new NullOutput);

    $snapshotStore = (fn () => $this->arraySnapshots)->call($initialRepository);
    expect($snapshotStore)->not()->toBeEmpty();

    expect($initialRepository->getEvaluation(
        $project->key,
        $environment->key,
        $flag->key,
        null,
        $evaluationAttributes,
        $flagSignature
    ))->not()->toBeNull();

    $queries = $this->recordDatabaseQueries(function () use ($project, $environment, $flag): void {
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
    });

    $selectQueries = array_filter($queries, static fn (array $query): bool => str_starts_with(strtolower((string) ($query['query'] ?? '')), 'select'));

    expect($selectQueries)->toBeEmpty();

    expect(Evaluation::query()->count())->toBe(2);

    expect($this->cachedEvaluation(
        $flag->fresh(),
        $project->key,
        $environment->key,
        null,
        $evaluationAttributes
    ))->not()->toBeNull();

    app()->forgetInstance(FlagCacheRepository::class);
});
