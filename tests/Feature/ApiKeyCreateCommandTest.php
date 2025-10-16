<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Phlag\Auth\ApiKeys\ApiCredentialHasher;
use Phlag\Commands\ApiKeys\CreateCommand;
use Phlag\Models\ApiCredential;
use Phlag\Models\Environment;
use Phlag\Models\Project;
use Symfony\Component\Console\Command\Command;
use Phlag\Auth\ApiKeys\TokenExchangeService;

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

it('creates an API credential with default scopes when none are supplied', function (): void {
    Str::createRandomStringsUsing(static fn (): string => 'test-generated-key-123456789');

    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', $this->project->key)
        ->expectsQuestion('Environment key', $this->environment->key)
        ->expectsQuestion('Credential name', 'CLI Credential')
        ->expectsQuestion('Scopes (comma separated, leave blank for full access)', '')
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
    expect($storedCredential->scopes)->toEqual(TokenExchangeService::DEFAULT_ROLES);
    expect($storedCredential->expires_at)->toBeNull();
    expect($storedCredential->is_active)->toBeTrue();
    expect($storedCredential->key_hash)->toBeString();

    expect(ApiCredentialHasher::verify($storedCredential, 'test-generated-key-123456789'))->toBeTrue();
});

it('creates an API credential with provided scopes when supplied', function (): void {
    Str::createRandomStringsUsing(static fn (): string => 'custom-key-987654321');

    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', $this->project->key)
        ->expectsQuestion('Environment key', $this->environment->key)
        ->expectsQuestion('Credential name', 'Custom Scope Credential')
        ->expectsQuestion('Scopes (comma separated, leave blank for full access)', 'projects.read,flags.evaluate')
        ->expectsQuestion('Expiration (ISO 8601, leave blank for none)', '')
        ->expectsOutputToContain('API key created successfully.')
        ->expectsOutputToContain('Store this API key securely:')
        ->expectsOutputToContain('custom-key-987654321')
        ->assertExitCode(Command::SUCCESS);

    /** @var ApiCredential|null $storedCredential */
    $storedCredential = ApiCredential::query()
        ->where('name', 'Custom Scope Credential')
        ->first();

    expect($storedCredential)->toBeInstanceOf(ApiCredential::class);

    /** @var ApiCredential $storedCredential */
    $storedCredential = $storedCredential;

    expect($storedCredential->scopes)->toEqual(['projects.read', 'flags.evaluate']);
    expect(ApiCredentialHasher::verify($storedCredential, 'custom-key-987654321'))->toBeTrue();
});

it('fails when the project cannot be found', function (): void {
    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', 'unknown-project')
        ->expectsOutputToContain('Project [unknown-project] was not found.')
        ->assertExitCode(Command::FAILURE);

    expect(ApiCredential::query()->count())->toBe(0);
});
