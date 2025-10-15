<?php

declare(strict_types=1);

namespace Phlag\Auth\ApiKeys;

use Phlag\Auth\Jwt\Token;
use Phlag\Models\Environment;
use Phlag\Models\Project;

final class TokenExchangeResult
{
    public const ERROR_PROJECT_NOT_FOUND = 'project_not_found';

    public const ERROR_ENVIRONMENT_NOT_FOUND = 'environment_not_found';

    public const ERROR_CREDENTIAL_INVALID = 'credential_invalid';

    public const ERROR_CREDENTIAL_INACTIVE = 'credential_inactive';

    public const ERROR_CREDENTIAL_EXPIRED = 'credential_expired';

    private function __construct(
        private readonly bool $successful,
        private readonly ?Token $token = null,
        private readonly ?Project $project = null,
        private readonly ?Environment $environment = null,
        private readonly ?int $expiresIn = null,
        /** @var array<int, string>|null */
        private readonly ?array $roles = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
    ) {}

    /**
     * @param  array<int, string>  $roles
     */
    public static function success(Project $project, Environment $environment, Token $token, int $expiresIn, array $roles): self
    {
        return new self(true, $token, $project, $environment, $expiresIn, $roles);
    }

    public static function failure(string $code, string $message): self
    {
        return new self(false, errorCode: $code, errorMessage: $message);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function token(): ?Token
    {
        return $this->token;
    }

    public function project(): ?Project
    {
        return $this->project;
    }

    public function environment(): ?Environment
    {
        return $this->environment;
    }

    public function expiresIn(): ?int
    {
        return $this->expiresIn;
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        return $this->roles ?? [];
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
