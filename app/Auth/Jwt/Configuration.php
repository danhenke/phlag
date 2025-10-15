<?php

declare(strict_types=1);

namespace Phlag\Auth\Jwt;

use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;
use function filter_var;
use function is_array;
use function is_string;
use function trim;

final class Configuration
{
    public const RSA_ALGORITHM = 'RS256';

    public const HMAC_ALGORITHM = 'HS256';

    private function __construct(
        private readonly ?KeyPair $activeKeyPair,
        private readonly ?PublicKey $previousKey,
        private readonly ?Secret $secret,
        private readonly int $ttl,
        private readonly int $clockSkew,
        /** @var array<int, string> */
        private readonly array $requiredClaims,
    ) {
        if ($this->activeKeyPair === null && $this->secret === null) {
            throw new RuntimeException(
                'JWT signing configuration is invalid. Provide RSA key material or a JWT_SECRET.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $keysConfig = $config['keys'] ?? [];

        if (! is_array($keysConfig)) {
            $keysConfig = [];
        }

        /** @var array<string, mixed> $keys */
        $keys = $keysConfig;

        $activeArray = [];

        if (is_array($keys['active'] ?? null)) {
            /** @var array<string, mixed> $activeArray */
            $activeArray = $keys['active'];
        }

        $previousArray = [];

        if (is_array($keys['previous'] ?? null)) {
            /** @var array<string, mixed> $previousArray */
            $previousArray = $keys['previous'];
        }

        $active = self::keyPairFromConfig($activeArray);
        $previous = self::previousKeyFromConfig($previousArray);
        $secret = self::secretFromConfig($keys['secret'] ?? null);

        $ttl = self::positiveInteger($config['ttl'] ?? 3600, 3600);
        $clockSkew = self::nonNegativeInteger($config['clock_skew'] ?? 60, 60);

        $requiredClaims = array_values(array_filter(
            array_map(
                static fn (mixed $claim): ?string => is_string($claim) ? trim($claim) : null,
                is_array($config['required_claims'] ?? null) ? $config['required_claims'] : []
            ),
            static fn (?string $claim): bool => $claim !== null && $claim !== ''
        ));

        if ($requiredClaims === []) {
            $requiredClaims = ['sub', 'iat', 'exp'];
        }

        return new self(
            activeKeyPair: $active,
            previousKey: $previous,
            secret: $secret,
            ttl: $ttl,
            clockSkew: $clockSkew,
            requiredClaims: $requiredClaims
        );
    }

    public function usesRsa(): bool
    {
        return $this->activeKeyPair !== null;
    }

    public function algorithm(): string
    {
        return $this->usesRsa() ? self::RSA_ALGORITHM : self::HMAC_ALGORITHM;
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    public function clockSkew(): int
    {
        return $this->clockSkew;
    }

    /**
     * @return array<int, string>
     */
    public function requiredClaims(): array
    {
        return $this->requiredClaims;
    }

    public function activeKeyPair(): ?KeyPair
    {
        return $this->activeKeyPair;
    }

    public function previousKey(): ?PublicKey
    {
        return $this->previousKey;
    }

    public function secret(): ?Secret
    {
        return $this->secret;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function keyPairFromConfig(array $config): ?KeyPair
    {
        $keyId = self::stringValue($config['id'] ?? null);
        $privateKey = self::stringValue($config['private_key'] ?? null);
        $publicKey = self::stringValue($config['public_key'] ?? null);

        if ($keyId === null && $privateKey === null && $publicKey === null) {
            return null;
        }

        if ($keyId === null || $privateKey === null || $publicKey === null) {
            throw new RuntimeException(
                'Incomplete RSA configuration. JWT_KEY_ID, JWT_PRIVATE_KEY, and JWT_PUBLIC_KEY must all be set.'
            );
        }

        return new KeyPair($keyId, $privateKey, $publicKey);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function previousKeyFromConfig(array $config): ?PublicKey
    {
        $keyId = self::stringValue($config['id'] ?? null);
        $publicKey = self::stringValue($config['public_key'] ?? null);

        if ($keyId === null && $publicKey === null) {
            return null;
        }

        if ($keyId === null || $publicKey === null) {
            throw new RuntimeException(
                'Incomplete previous key configuration. Provide both JWT_PREVIOUS_KEY_ID and JWT_PREVIOUS_PUBLIC_KEY.'
            );
        }

        return new PublicKey($keyId, $publicKey);
    }

    private static function secretFromConfig(mixed $value): ?Secret
    {
        $secret = self::stringValue($value);

        if ($secret === null) {
            return null;
        }

        return new Secret($secret);
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function positiveInteger(mixed $value, int $default): int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT);

        if (! is_int($validated) || $validated <= 0) {
            return $default;
        }

        return $validated;
    }

    private static function nonNegativeInteger(mixed $value, int $default): int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT);

        if (! is_int($validated) || $validated < 0) {
            return $default;
        }

        return $validated;
    }
}
