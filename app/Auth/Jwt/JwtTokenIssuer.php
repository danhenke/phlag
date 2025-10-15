<?php

declare(strict_types=1);

namespace Phlag\Auth\Jwt;

use Firebase\JWT\JWT;
use Phlag\Support\Clock\Clock;
use RuntimeException;

use function array_key_exists;

final class JwtTokenIssuer
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public function issue(array $claims, ?int $ttlOverride = null): Token
    {
        $now = $this->clock->now()->getTimestamp();
        $ttl = $ttlOverride !== null && $ttlOverride > 0
            ? $ttlOverride
            : $this->configuration->ttl();

        if (! array_key_exists('iat', $claims)) {
            $claims['iat'] = $now;
        }

        if (! array_key_exists('exp', $claims)) {
            $claims['exp'] = $now + $ttl;
        }

        foreach ($this->configuration->requiredClaims() as $claim) {
            if (! array_key_exists($claim, $claims)) {
                throw new RuntimeException("Cannot issue JWT without required claim [{$claim}].");
            }
        }

        if ($this->configuration->usesRsa()) {
            $keyPair = $this->configuration->activeKeyPair();

            if ($keyPair === null) {
                throw new RuntimeException('RSA keys are not configured for JWT issuance.');
            }

            $token = JWT::encode(
                $claims,
                $keyPair->privateKey(),
                Configuration::RSA_ALGORITHM,
                $keyPair->keyId()
            );

            return new Token($token);
        }

        $secret = $this->configuration->secret();

        if ($secret === null) {
            throw new RuntimeException('JWT secret is not configured.');
        }

        $token = JWT::encode(
            $claims,
            $secret->value(),
            Configuration::HMAC_ALGORITHM
        );

        return new Token($token);
    }
}
