<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Phlag\Auth\ApiKeys\ApiCredentialHasher;
use Phlag\Auth\Jwt\Configuration;
use Phlag\Auth\Jwt\JwtTokenIssuer;
use Phlag\Auth\Jwt\JwtTokenVerifier;
use Phlag\Models\ApiCredential;
use Phlag\Models\Environment;
use Phlag\Models\Project;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\TestKeys;

beforeEach(function (): void {
    $this->artisan('migrate:fresh')->assertExitCode(0);

    config()->set('jwt', [
        'keys' => [
            'active' => [
                'id' => TestKeys::ACTIVE_KEY_ID,
                'private_key' => TestKeys::activePrivateKey(),
                'public_key' => TestKeys::activePublicKey(),
            ],
            'secret' => null,
        ],
        'ttl' => 600,
        'clock_skew' => 0,
    ]);

    app()->forgetInstance(Configuration::class);
    app()->forgetInstance(JwtTokenIssuer::class);
    app()->forgetInstance(JwtTokenVerifier::class);

    $this->project = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'demo-project',
        'name' => 'Demo Project',
    ]);

    $this->environment = Environment::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $this->project->id,
        'key' => 'production',
        'name' => 'Production',
        'is_default' => true,
    ]);

    $this->apiKey = 'test-api-key-123456';
    $this->credentialScopes = [
        'projects.read',
        'projects.manage',
        'environments.read',
        'environments.manage',
        'flags.read',
        'flags.manage',
        'flags.evaluate',
        'cache.warm',
    ];
});

it('issues JWTs for valid project credentials', function (): void {
    $credential = ApiCredential::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $this->project->id,
        'environment_id' => $this->environment->id,
        'name' => 'Demo Production Credential',
        'scopes' => $this->credentialScopes,
        'key_hash' => ApiCredentialHasher::make($this->apiKey),
        'is_active' => true,
    ]);

    $response = $this->postJson('/v1/auth/token', [
        'project' => $this->project->key,
        'environment' => $this->environment->key,
        'api_key' => $this->apiKey,
    ]);

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('project', $this->project->key)
            ->where('environment', $this->environment->key)
            ->where('token_type', 'Bearer')
            ->where('expires_in', 600)
            ->where('roles', $this->credentialScopes)
            ->has('token')
        );

    $token = $response->json('token');

    expect($token)->toBeString();

    $claims = JWT::decode(
        $token,
        new Key(TestKeys::activePublicKey(), Configuration::RSA_ALGORITHM)
    );

    expect($claims->sub)->toBe('api_credential:'.$credential->id)
        ->and($claims->project_id)->toBe($this->project->id)
        ->and($claims->project_key)->toBe($this->project->key)
        ->and($claims->environment_id)->toBe($this->environment->id)
        ->and($claims->environment_key)->toBe($this->environment->key)
        ->and($claims->roles)->toEqual($this->credentialScopes)
        ->and($claims->iat)->toBeInt()
        ->and($claims->exp - $claims->iat)->toBe(600);

    $header = json_decode(
        base64_decode(explode('.', $token)[0], true),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($header['kid'])->toBe(TestKeys::ACTIVE_KEY_ID)
        ->and($header['alg'])->toBe(Configuration::RSA_ALGORITHM);
});

it('rejects requests with unknown API keys', function (): void {
    ApiCredential::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $this->project->id,
        'environment_id' => $this->environment->id,
        'name' => 'Demo Production Credential',
        'scopes' => $this->credentialScopes,
        'key_hash' => ApiCredentialHasher::make($this->apiKey),
        'is_active' => true,
    ]);

    $response = $this->postJson('/v1/auth/token', [
        'project' => $this->project->key,
        'environment' => $this->environment->key,
        'api_key' => 'wrong-'.$this->project->key,
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED)
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('error.code', 'unauthorized')
            ->where('error.status', Response::HTTP_UNAUTHORIZED)
            ->where('error.message', 'Authentication failed for the provided API key.')
        );
});

it('returns not found when environment does not belong to project', function (): void {
    $otherProject = Project::query()->create([
        'id' => (string) Str::uuid(),
        'key' => 'other-project',
        'name' => 'Other Project',
    ]);

    Environment::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $otherProject->id,
        'key' => 'staging',
        'name' => 'Staging',
        'is_default' => false,
    ]);

    $response = $this->postJson('/v1/auth/token', [
        'project' => $this->project->key,
        'environment' => 'staging',
        'api_key' => $this->apiKey,
    ]);

    $response->assertStatus(Response::HTTP_NOT_FOUND)
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('error.code', 'resource_not_found')
            ->where('error.status', Response::HTTP_NOT_FOUND)
            ->where('error.message', 'Environment not found for project.')
        );
});

it('rejects inactive API credentials', function (): void {
    ApiCredential::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $this->project->id,
        'environment_id' => $this->environment->id,
        'name' => 'Inactive Credential',
        'scopes' => $this->credentialScopes,
        'key_hash' => ApiCredentialHasher::make($this->apiKey),
        'is_active' => false,
    ]);

    $response = $this->postJson('/v1/auth/token', [
        'project' => $this->project->key,
        'environment' => $this->environment->key,
        'api_key' => $this->apiKey,
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED)
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('error.code', 'unauthorized')
            ->where('error.message', 'The API key is inactive.')
        );
});

it('rejects expired API credentials', function (): void {
    ApiCredential::query()->create([
        'id' => (string) Str::uuid(),
        'project_id' => $this->project->id,
        'environment_id' => $this->environment->id,
        'name' => 'Expired Credential',
        'scopes' => $this->credentialScopes,
        'key_hash' => ApiCredentialHasher::make($this->apiKey),
        'is_active' => true,
        'expires_at' => Carbon::now()->subMinutes(5),
    ]);

    $response = $this->postJson('/v1/auth/token', [
        'project' => $this->project->key,
        'environment' => $this->environment->key,
        'api_key' => $this->apiKey,
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED)
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('error.code', 'unauthorized')
            ->where('error.message', 'The API key has expired.')
        );
});
