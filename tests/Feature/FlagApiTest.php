<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Phlag\Models\Flag;
use Phlag\Models\Project;

beforeEach(function (): void {
    $this->artisan('migrate:fresh')->assertExitCode(0);
});

it('creates a flag for a project', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'checkout-service',
        'name' => 'Checkout Service',
    ]);

    $payload = [
        'key' => 'checkout-redesign',
        'name' => 'Checkout Redesign',
        'description' => 'Serve the redesigned checkout funnel.',
        'is_enabled' => true,
        'variants' => [
            ['key' => 'control', 'weight' => 40],
            ['key' => 'variant', 'weight' => 60],
        ],
        'rules' => [
            [
                'match' => ['country' => ['US', 'CA']],
                'variant' => 'variant',
                'rollout' => 75,
            ],
        ],
    ];

    $response = $this->postJson("/v1/projects/{$project->key}/flags", $payload);

    $response->assertCreated()
        ->assertHeader('Location', route('projects.flags.show', [
            'project' => $project,
            'flag' => $payload['key'],
        ]))
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.key', $payload['key'])
            ->where('data.name', $payload['name'])
            ->where('data.description', $payload['description'])
            ->where('data.is_enabled', true)
            ->where('data.variants.0.key', 'control')
            ->where('data.rules.0.match.country.0', 'US')
            ->etc()
        );

    expect(Flag::query()->where('project_id', $project->id)->where('key', $payload['key'])->exists())->toBeTrue();
});

it('lists flags for a project with pagination metadata', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'recommendations',
        'name' => 'Recommendations',
    ]);

    collect([
        ['key' => 'homepage-recommendations', 'name' => 'Homepage Recommendations'],
        ['key' => 'search-recommendations', 'name' => 'Search Recommendations'],
        ['key' => 'cart-cross-sell', 'name' => 'Cart Cross Sell'],
    ])->each(function (array $flag) use ($project): void {
        $project->flags()->create([
            'id' => (string) Str::uuid(),
            'key' => $flag['key'],
            'name' => $flag['name'],
            'variants' => [
                ['key' => 'off', 'weight' => 100],
            ],
            'rules' => [],
        ]);
    });

    $response = $this->getJson("/v1/projects/{$project->key}/flags?per_page=2");

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', 2)
            ->has('meta', fn (AssertableJson $meta) => $meta
                ->where('per_page', 2)
                ->where('total', 3)
                ->etc()
            )
            ->has('links')
        );
});

it('updates and deletes an existing flag', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'billing',
        'name' => 'Billing',
    ]);

    $flag = $project->flags()->create([
        'id' => (string) Str::uuid(),
        'key' => 'billing-rollout',
        'name' => 'Billing Rollout',
        'is_enabled' => true,
        'variants' => [
            ['key' => 'control', 'weight' => 100],
        ],
        'rules' => [
            [
                'match' => ['segment' => ['internal']],
                'variant' => 'control',
                'rollout' => 100,
            ],
        ],
    ]);

    $updateResponse = $this->patchJson("/v1/projects/{$project->key}/flags/{$flag->key}", [
        'name' => 'Billing GA',
        'is_enabled' => false,
        'rules' => [
            [
                'match' => ['segment' => ['customers']],
                'variant' => 'control',
                'rollout' => 50,
            ],
        ],
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.name', 'Billing GA')
        ->assertJsonPath('data.is_enabled', false)
        ->assertJsonPath('data.rules.0.rollout', 50);

    $this->getJson("/v1/projects/{$project->key}/flags/{$flag->key}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Billing GA');

    $this->deleteJson("/v1/projects/{$project->key}/flags/{$flag->key}")
        ->assertNoContent();

    expect(Flag::query()->where('project_id', $project->id)->exists())->toBeFalse();
});

it('validates uniqueness constraints for flag keys per project', function (): void {
    $projectA = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'alpha',
        'name' => 'Alpha',
    ]);

    $projectB = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'beta',
        'name' => 'Beta',
    ]);

    $projectA->flags()->create([
        'id' => (string) Str::uuid(),
        'key' => 'shared-flag',
        'name' => 'Shared Flag',
        'variants' => [
            ['key' => 'off', 'weight' => 100],
        ],
        'rules' => [],
    ]);

    $payload = [
        'key' => 'shared-flag',
        'name' => 'Duplicate Flag',
        'variants' => [
            ['key' => 'off', 'weight' => 100],
        ],
        'rules' => [],
    ];

    $this->postJson("/v1/projects/{$projectA->key}/flags", $payload)
        ->assertStatus(422)
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('message')
            ->has('errors.key')
            ->etc()
        );

    $this->postJson("/v1/projects/{$projectB->key}/flags", $payload)
        ->assertCreated();

    expect(Flag::query()->where('project_id', $projectB->id)->where('key', 'shared-flag')->exists())->toBeTrue();
});

it('validates rule schema and rollout bounds', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'gamma',
        'name' => 'Gamma',
    ]);

    $response = $this->postJson("/v1/projects/{$project->key}/flags", [
        'key' => 'invalid-schema',
        'name' => 'Invalid Schema',
        'variants' => [
            ['key' => 'control', 'weight' => 100],
        ],
        'rules' => [
            [
                'match' => ['country' => []],
                'variant' => '',
                'rollout' => 200,
            ],
        ],
    ]);

    $response->assertStatus(422);

    $errors = $response->json('errors');

    expect($errors)
        ->toHaveKey('rules.0.match.country')
        ->toHaveKey('rules.0.variant')
        ->toHaveKey('rules.0.rollout');
});
