<?php

declare(strict_types=1);

namespace Phlag\Auth\ApiKeys;

use Phlag\Auth\Jwt\Configuration as JwtConfiguration;
use Phlag\Auth\Jwt\JwtTokenIssuer;
use Phlag\Auth\Rbac\RoleRegistry;
use Phlag\Models\ApiCredential;
use Phlag\Models\Environment;
use Phlag\Models\Project;
use Phlag\Support\Clock\Clock;

use function is_array;
use function sprintf;

final class TokenExchangeService
{
    public function __construct(
        private readonly JwtTokenIssuer $issuer,
        private readonly JwtConfiguration $configuration,
        private readonly Clock $clock,
        private readonly RoleRegistry $roleRegistry,
    ) {}

    public function exchange(string $projectKey, string $environmentKey, string $apiKey): TokenExchangeResult
    {
        /** @var Project|null $project */
        $project = Project::query()->where('key', $projectKey)->first();

        if ($project === null) {
            return TokenExchangeResult::failure(
                TokenExchangeResult::ERROR_PROJECT_NOT_FOUND,
                'The specified project does not exist.'
            );
        }

        /** @var Environment|null $environment */
        $environment = Environment::query()
            ->where('project_id', $project->id)
            ->where('key', $environmentKey)
            ->first();

        if ($environment === null) {
            return TokenExchangeResult::failure(
                TokenExchangeResult::ERROR_ENVIRONMENT_NOT_FOUND,
                'The specified environment does not exist for the project.'
            );
        }

        /** @var ApiCredential|null $credential */
        $credential = ApiCredential::query()
            ->where('project_id', $project->id)
            ->where('environment_id', $environment->id)
            ->where('key_hash', ApiCredentialHasher::make($apiKey))
            ->first();

        if ($credential === null) {
            return TokenExchangeResult::failure(
                TokenExchangeResult::ERROR_CREDENTIAL_INVALID,
                'The provided API key is not recognized for this project and environment.'
            );
        }

        if (! ApiCredentialHasher::verify($credential, $apiKey)) {
            return TokenExchangeResult::failure(
                TokenExchangeResult::ERROR_CREDENTIAL_INVALID,
                'The provided API key is not recognized for this project and environment.'
            );
        }

        if (! $credential->is_active) {
            return TokenExchangeResult::failure(
                TokenExchangeResult::ERROR_CREDENTIAL_INACTIVE,
                'The provided API key has been deactivated.'
            );
        }

        if ($credential->expires_at !== null) {
            $now = $this->clock->now();

            if ($credential->expires_at->toDateTimeImmutable() <= $now) {
                return TokenExchangeResult::failure(
                    TokenExchangeResult::ERROR_CREDENTIAL_EXPIRED,
                    'The provided API key has expired.'
                );
            }
        }

        $roles = $credential->roles;

        if (! is_array($roles) || $roles === []) {
            $roles = $this->roleRegistry->defaultRoles();
        } else {
            $roles = $this->roleRegistry->resolveRoles($roles);
        }

        $permissions = $this->roleRegistry->permissionsForRoles($roles);

        $token = $this->issuer->issue([
            'sub' => sprintf('api_credential:%s', $credential->id),
            'project_id' => $project->id,
            'project_key' => $project->key,
            'environment_id' => $environment->id,
            'environment_key' => $environment->key,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);

        $ttl = $this->configuration->ttl();

        return TokenExchangeResult::success(
            $project,
            $environment,
            $token,
            $ttl > 0 ? $ttl : 0,
            $roles,
            $permissions
        );
    }
}
