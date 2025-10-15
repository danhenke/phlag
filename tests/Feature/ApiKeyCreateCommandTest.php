<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Phlag\Auth\ApiKeys\ApiCredentialHasher;
use Phlag\Commands\ApiKeys\CreateCommand;
use Phlag\Models\ApiCredential;
use Phlag\Models\Environment;
use Phlag\Models\Project;
use Symfony\Component\Console\Command\Command;

beforeEach(function (): void {
    $this->artisan('migrate:fresh')->assertExitCode(0);

    $this->project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'search',
        'name' => 'Search Service',
    ]);

    $this->environment = Environment::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $this->project->id,
        'key' => 'production',
        'name' => 'Production',
        'is_default' => true,
    ]);
});

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

it('creates an API credential for a project environment', function (): void {
    Str::createRandomStringsUsing(static fn (): string => 'test-generated-key-123456789');

    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', $this->project->key)
        ->expectsQuestion('Environment key', $this->environment->key)
        ->expectsQuestion('Credential name', 'CLI Credential')
        ->expectsQuestion(
            'Scopes (comma separated, e.g. projects.read,environments.read)',
            'projects.read,flags.evaluate'
        )
        ->expectsQuestion('Expiration (ISO 8601, leave blank for none)', '')
        ->expectsOutputToContain('API key created successfully.')
        ->expectsOutputToContain('Store this API key securely:')
        ->expectsOutputToContain('test-generated-key-123456789')
        ->assertExitCode(Command::SUCCESS);

    $storedCredential = ApiCredential::query()->first();

    expect($storedCredential)->toBeInstanceOf(ApiCredential::class);

    /** @var ApiCredential $storedCredential */
    $storedCredential = $storedCredential;

    expect($storedCredential->project_id)->toBe($this->project->id);
    expect($storedCredential->environment_id)->toBe($this->environment->id);
    expect($storedCredential->name)->toBe('CLI Credential');
    expect($storedCredential->scopes)->toEqual(['projects.read', 'flags.evaluate']);
    expect($storedCredential->expires_at)->toBeNull();
    expect($storedCredential->is_active)->toBeTrue();
    expect($storedCredential->key_hash)->toBeString();

    expect(ApiCredentialHasher::verify($storedCredential, 'test-generated-key-123456789'))->toBeTrue();
});

it('fails when the project cannot be found', function (): void {
    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', 'unknown-project')
        ->expectsOutputToContain('Project [unknown-project] was not found.')
        ->assertExitCode(Command::FAILURE);

    expect(ApiCredential::query()->count())->toBe(0);
});
