<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Phlag\Models\Environment;
use Phlag\Models\Project;

beforeEach(function (): void {
    $this->artisan('migrate:fresh')->assertExitCode(0);
});

it('creates a project via the API', function (): void {
    $payload = [
        'key' => 'checkout-service',
        'name' => 'Checkout Service',
        'description' => 'Tracks checkout experiments.',
        'metadata' => [
            'owner' => 'experiments@example.com',
        ],
    ];

    $response = $this->postJson('/v1/projects', $payload);

    $response->assertCreated()
        ->assertHeader('Location', route('projects.show', ['project' => 'checkout-service']))
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.key', $payload['key'])
            ->where('data.name', $payload['name'])
            ->where('data.description', $payload['description'])
            ->where('data.metadata.owner', 'experiments@example.com')
            ->etc()
        );

    expect(Project::query()->where('key', 'checkout-service')->exists())->toBeTrue();
});

it('lists projects with pagination metadata', function (): void {
    collect(range(1, 3))->each(function (int $number): void {
        Project::query()->create([
            'id' => (string) Str::uuid(),
            'key' => "project-{$number}",
            'name' => "Project {$number}",
        ]);
    });

    $response = $this->getJson('/v1/projects?per_page=2');

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

it('updates and deletes an existing project', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'billing-service',
        'name' => 'Billing Service',
    ]);

    $updateResponse = $this->patchJson("/v1/projects/{$project->key}", [
        'name' => 'Billing Platform',
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.name', 'Billing Platform');

    $this->deleteJson("/v1/projects/{$project->key}")
        ->assertNoContent();

    expect(Project::query()->where('key', 'billing-service')->exists())->toBeFalse();
});

it('validates uniqueness constraints for project keys', function (): void {
    Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'analytics',
        'name' => 'Analytics',
    ]);

    $this->postJson('/v1/projects', [
        'key' => 'analytics',
        'name' => 'Duplicate Analytics',
    ])->assertStatus(422)
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('message')
            ->has('errors.key')
        );
});

it('manages environments for a project', function (): void {
    $project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'experiments',
        'name' => 'Experiments',
    ]);

    $primaryResponse = $this->postJson("/v1/projects/{$project->key}/environments", [
        'key' => 'production',
        'name' => 'Production',
        'is_default' => true,
    ]);

    $primaryResponse->assertCreated()
        ->assertJsonPath('data.is_default', true);

    $secondaryResponse = $this->postJson("/v1/projects/{$project->key}/environments", [
        'key' => 'staging',
        'name' => 'Staging',
        'is_default' => true,
    ]);

    $secondaryResponse->assertCreated()
        ->assertJsonPath('data.key', 'staging')
        ->assertJsonPath('data.is_default', true);

    $environments = Environment::query()
        ->where('project_id', $project->id)
        ->pluck('is_default', 'key');

    expect($environments['production'])->toBeFalse()
        ->and($environments['staging'])->toBeTrue();

    $listResponse = $this->getJson("/v1/projects/{$project->key}/environments");

    $listResponse->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', 2)
            ->has('meta')
            ->has('links')
        );

    $updateResponse = $this->patchJson("/v1/projects/{$project->key}/environments/staging", [
        'name' => 'Staging Updated',
        'is_default' => false,
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.name', 'Staging Updated')
        ->assertJsonPath('data.is_default', false);

    $this->deleteJson("/v1/projects/{$project->key}/environments/staging")
        ->assertNoContent();

    expect(Environment::query()->where('project_id', $project->id)->count())->toBe(1);
});
