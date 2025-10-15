<?php

declare(strict_types=1);

namespace Phlag\Auth\Jwt;

use RuntimeException;

/**
 * Immutable result describing the outcome of verifying a JWT.
 */
final class TokenVerificationResult
{
    private function __construct(
        private readonly bool $valid,
        private readonly ?TokenClaims $claims,
        private readonly ?string $code,
        private readonly ?string $message,
        /** @var array<string, mixed> */
        private readonly array $context,
    ) {
        if ($this->valid && $this->claims === null) {
            throw new RuntimeException('Valid verification results must include claims.');
        }
    }

    public static function success(TokenClaims $claims): self
    {
        return new self(true, $claims, null, null, []);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function failure(string $code, string $message, array $context = []): self
    {
        return new self(false, null, $code, $message, $context);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function claims(): TokenClaims
    {
        if (! $this->valid || $this->claims === null) {
            throw new RuntimeException('Token verification failed; no claims available.');
        }

        return $this->claims;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
