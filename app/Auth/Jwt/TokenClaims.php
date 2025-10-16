<?php

declare(strict_types=1);

namespace Phlag\Auth\Jwt;

use InvalidArgumentException;
use stdClass;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function in_array;
use function trim;

final class TokenClaims
{
    /**
     * @param  array<string, mixed>  $claims
     */
    private function __construct(private readonly array $claims)
    {
        if ($claims === []) {
            throw new InvalidArgumentException('Token claims cannot be empty.');
        }
    }

    /**
     * @param  array<string, mixed>|stdClass  $claims
     */
    public static function fromPayload(array|stdClass $claims): self
    {
        if ($claims instanceof stdClass) {
            $claims = (array) $claims;
        }

        if ($claims === []) {
            throw new InvalidArgumentException('Token claims payload must contain key/value pairs.');
        }

        /** @var array<string, mixed> $claimsArray */
        $claimsArray = $claims;

        return new self($claimsArray);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->claims;
    }

    public function get(string $claim, mixed $default = null): mixed
    {
        return $this->claims[$claim] ?? $default;
    }

    public function has(string $claim): bool
    {
        return array_key_exists($claim, $this->claims);
    }

    public function subject(): ?string
    {
        $subject = $this->claims['sub'] ?? null;

        return is_string($subject) ? $subject : null;
    }

    public function expiresAt(): ?int
    {
        $expiry = $this->claims['exp'] ?? null;

        return is_int($expiry) ? $expiry : null;
    }

    public function issuedAt(): ?int
    {
        $issuedAt = $this->claims['iat'] ?? null;

        return is_int($issuedAt) ? $issuedAt : null;
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        $roles = $this->claims['roles'] ?? [];

        if (! is_array($roles)) {
            return [];
        }

        $normalized = array_values(array_filter(array_map(
            static function ($role): ?string {
                if (! is_string($role)) {
                    return null;
                }

                $trimmed = trim($role);

                return $trimmed === '' ? null : $trimmed;
            },
            $roles
        )));

        return $normalized;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (! $this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }
}
