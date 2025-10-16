<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Phlag\Auth\ApiKeys\ApiCredentialHasher;
use Phlag\Auth\Rbac\RoleRegistry;
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

it('creates an API credential with default roles when none are supplied', function (): void {
    Str::createRandomStringsUsing(static fn (): string => 'test-generated-key-123456789');

    /** @var RoleRegistry $registry */
    $registry = app(RoleRegistry::class);
    $defaultRoles = $registry->defaultRoles();
    $defaultRoleList = implode(', ', $defaultRoles);

    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', $this->project->key)
        ->expectsQuestion('Environment key', $this->environment->key)
        ->expectsQuestion('Credential name', 'CLI Credential')
        ->expectsQuestion('Roles (comma separated, press enter for default access)', '')
        ->expectsOutputToContain('No roles provided; granting default roles: '.$defaultRoleList)
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
    expect($storedCredential->roles)->toEqual($defaultRoles);
    expect($storedCredential->expires_at)->toBeNull();
    expect($storedCredential->is_active)->toBeTrue();
    expect($storedCredential->key_hash)->toBeString();

    expect(ApiCredentialHasher::verify($storedCredential, 'test-generated-key-123456789'))->toBeTrue();
});

it('creates an API credential with provided roles when supplied', function (): void {
    Str::createRandomStringsUsing(static fn (): string => 'custom-key-987654321');

    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', $this->project->key)
        ->expectsQuestion('Environment key', $this->environment->key)
        ->expectsQuestion('Credential name', 'Custom Role Credential')
        ->expectsQuestion('Roles (comma separated, press enter for default access)', 'project.viewer,environment.operator')
        ->expectsQuestion('Expiration (ISO 8601, leave blank for none)', '')
        ->expectsOutputToContain('API key created successfully.')
        ->expectsOutputToContain('Store this API key securely:')
        ->expectsOutputToContain('custom-key-987654321')
        ->assertExitCode(Command::SUCCESS);

    /** @var ApiCredential|null $storedCredential */
    $storedCredential = ApiCredential::query()
        ->where('name', 'Custom Role Credential')
        ->first();

    expect($storedCredential)->toBeInstanceOf(ApiCredential::class);

    /** @var ApiCredential $storedCredential */
    $storedCredential = $storedCredential;

    expect($storedCredential->roles)->toEqual(['project.viewer', 'environment.operator']);
    expect(ApiCredentialHasher::verify($storedCredential, 'custom-key-987654321'))->toBeTrue();
});

it('rejects credentials when roles include unsupported values', function (): void {
    /** @var RoleRegistry $registry */
    $registry = app(RoleRegistry::class);
    $allowedRolesList = implode(', ', array_keys($registry->definitions()));

    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', $this->project->key)
        ->expectsQuestion('Environment key', $this->environment->key)
        ->expectsQuestion('Credential name', 'Invalid Role Credential')
        ->expectsQuestion('Roles (comma separated, press enter for default access)', 'project.viewer,invalid.role')
        ->expectsOutputToContain('Unknown role(s): invalid.role. Allowed roles: '.$allowedRolesList)
        ->assertExitCode(Command::FAILURE);

    expect(ApiCredential::query()->where('name', 'Invalid Role Credential')->exists())->toBeFalse();
});

it('fails when the project cannot be found', function (): void {
    $this->artisan(CreateCommand::class)
        ->expectsQuestion('Project key', 'unknown-project')
        ->expectsOutputToContain('Project [unknown-project] was not found.')
        ->assertExitCode(Command::FAILURE);

    expect(ApiCredential::query()->count())->toBe(0);
});
