<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Phlag\Models\Environment;
use Phlag\Models\Evaluation;
use Phlag\Models\Flag;
use Phlag\Models\Project;

beforeEach(function (): void {
    $this->artisan('migrate:fresh')->assertExitCode(0);
});

it('evaluates a flag using rollout rules and persists the evaluation', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'checkout-service',
        'name' => 'Checkout Service',
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
        'key' => 'checkout-redesign',
        'name' => 'Checkout Redesign',
        'is_enabled' => true,
        'variants' => [
            ['key' => 'control', 'weight' => 40],
            ['key' => 'variant', 'weight' => 60],
        ],
        'rules' => [
            [
                'match' => ['country' => ['US']],
                'variant' => 'variant',
                'rollout' => 75,
            ],
        ],
    ]);

    $userId = findUserIdentifier(
        $project->key,
        $environment->key,
        $flag->key,
        'variant',
        static fn (int $bucket): bool => $bucket <= 75
    );

    $response = $this->getJson(sprintf(
        '/v1/evaluate?project=%s&env=%s&flag=%s&user_id=%s&country=US',
        $project->key,
        $environment->key,
        $flag->key,
        $userId
    ));

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.flag.key', $flag->key)
            ->where('data.project.key', $project->key)
            ->where('data.environment.key', $environment->key)
            ->where('data.user.identifier', $userId)
            ->where('data.context.country', 'US')
            ->where('data.result.variant', 'variant')
            ->where('data.result.reason', 'matched_country_rollout')
            ->where('data.result.rollout', 75)
            ->where('data.result.bucket', fn ($bucket) => is_int($bucket) && $bucket >= 1 && $bucket <= 75)
            ->has('data.id')
            ->has('data.evaluated_at')
        );

    expect(Evaluation::query()->count())->toBe(1);
});

it('falls back to the default variant when rollout gating is not satisfied', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'pricing-service',
        'name' => 'Pricing Service',
    ]);

    $environment = Environment::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $project->id,
        'key' => 'staging',
        'name' => 'Staging',
        'is_default' => false,
    ]);

    $flag = Flag::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $project->id,
        'key' => 'dynamic-pricing',
        'name' => 'Dynamic Pricing',
        'is_enabled' => true,
        'variants' => [
            ['key' => 'control', 'weight' => 100],
        ],
        'rules' => [
            [
                'match' => ['segment' => ['beta']],
                'variant' => 'control',
                'rollout' => 10,
            ],
        ],
    ]);

    $userId = findUserIdentifier(
        $project->key,
        $environment->key,
        $flag->key,
        'control',
        static fn (int $bucket): bool => $bucket > 10
    );

    $response = $this->getJson(sprintf(
        '/v1/evaluate?project=%s&env=%s&flag=%s&user_id=%s&segment=beta',
        $project->key,
        $environment->key,
        $flag->key,
        $userId
    ));

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.result.variant', 'control')
            ->where('data.result.reason', 'fallback_default')
            ->where('data.result.rollout', 0)
            ->missing('data.result.bucket')
        );
});

it('returns a disabled reason when the flag is turned off', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'homepage',
        'name' => 'Homepage',
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
        'key' => 'recommendations',
        'name' => 'Homepage Recommendations',
        'is_enabled' => false,
        'variants' => [
            ['key' => 'off', 'weight' => 100],
        ],
        'rules' => [],
    ]);

    $this->getJson(sprintf(
        '/v1/evaluate?project=%s&env=%s&flag=%s&country=US',
        $project->key,
        $environment->key,
        $flag->key
    ))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.result.reason', 'flag_disabled')
            ->where('data.result.variant', 'off')
            ->where('data.result.rollout', 0)
        );
});

it('reuses cached snapshots for repeated evaluations', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'search',
        'name' => 'Search',
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
        'key' => 'search-ui',
        'name' => 'Search UI',
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

    $connection = DB::connection();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    $queries = [];

    try {
        $this->getJson(sprintf(
            '/v1/evaluate?project=%s&env=%s&flag=%s&locale=en-US',
            $project->key,
            $environment->key,
            $flag->key
        ))
            ->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.flag.key', $flag->key)
                ->where('data.environment.key', $environment->key)
                ->where('data.project.key', $project->key)
            );
    } finally {
        $queries = $connection->getQueryLog();
        $connection->flushQueryLog();
        $connection->disableQueryLog();
    }

    $selectQueries = array_filter($queries, static fn (array $query): bool => str_starts_with(strtolower($query['query'] ?? ''), 'select'));

    $flagSelects = array_filter($selectQueries, static fn (array $query): bool => str_contains(strtolower($query['query'] ?? ''), 'from "flags"'));
    $projectSelects = array_filter($selectQueries, static fn (array $query): bool => str_contains(strtolower($query['query'] ?? ''), 'from "projects"'));
    $environmentSelects = array_filter($selectQueries, static fn (array $query): bool => str_contains(strtolower($query['query'] ?? ''), 'from "environments"'));

    expect($flagSelects)->toHaveCount(1);
    expect($projectSelects)->toBeEmpty();
    expect($environmentSelects)->toBeEmpty();

    expect(Evaluation::query()->count())->toBe(2);
});

it('invalidates cached evaluations when flag configuration changes', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'notifications',
        'name' => 'Notifications',
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
        'key' => 'push-enabled',
        'name' => 'Push Notifications',
        'is_enabled' => true,
        'variants' => [
            ['key' => 'enabled', 'weight' => 100],
        ],
        'rules' => [],
    ]);

    $this->getJson(sprintf(
        '/v1/evaluate?project=%s&env=%s&flag=%s',
        $project->key,
        $environment->key,
        $flag->key
    ))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.result.variant', 'enabled')
            ->where('data.result.reason', 'fallback_default')
        );

    $flag->update(['is_enabled' => false]);

    $this->getJson(sprintf(
        '/v1/evaluate?project=%s&env=%s&flag=%s',
        $project->key,
        $environment->key,
        $flag->key
    ))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.result.variant', 'enabled')
            ->where('data.result.reason', 'flag_disabled')
        );
});

it('returns an error when the requested environment does not exist', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'payments',
        'name' => 'Payments',
    ]);

    Flag::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $project->id,
        'key' => 'payments-v2',
        'name' => 'Payments V2',
        'is_enabled' => true,
        'variants' => [
            ['key' => 'off', 'weight' => 100],
        ],
        'rules' => [],
    ]);

    $this->getJson(sprintf(
        '/v1/evaluate?project=%s&env=%s&flag=%s',
        $project->key,
        'non-existent',
        'payments-v2'
    ))
        ->assertNotFound()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('error.code', 'resource_not_found')
            ->where('error.status', 404)
        );
});

/**
 * Select a deterministic user identifier that maps to the desired rollout bucket.
 */
function findUserIdentifier(string $projectKey, string $environmentKey, string $flagKey, string $variantKey, callable $predicate): string
{
    foreach (range(1, 1000) as $suffix) {
        $candidate = sprintf('user-%d', $suffix);
        $bucket = computeBucket($projectKey, $environmentKey, $flagKey, $variantKey, $candidate);

        if ($predicate($bucket) === true) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to locate a user identifier matching rollout requirements.');
}

/**
 * Compute the rollout bucket for a given user/flag combination.
 */
function computeBucket(string $projectKey, string $environmentKey, string $flagKey, string $variantKey, string $userIdentifier): int
{
    $seed = implode('|', [
        $projectKey,
        $environmentKey,
        $flagKey,
        $variantKey,
        $userIdentifier,
    ]);

    $hash = crc32($seed);
    $numeric = (int) sprintf('%u', $hash);

    return ($numeric % 100) + 1;
}
