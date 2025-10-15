<?php

declare(strict_types=1);

namespace Phlag\Auth\Jwt;

use InvalidArgumentException;

final class KeyPair
{
    public function __construct(
        private readonly string $keyId,
        private readonly string $privateKey,
        private readonly string $publicKey,
    ) {
        if ($keyId === '') {
            throw new InvalidArgumentException('The JWT key id must not be empty.');
        }

        if ($privateKey === '' || $publicKey === '') {
            throw new InvalidArgumentException('The JWT RSA key material must not be empty.');
        }
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    public function privateKey(): string
    {
        return $this->privateKey;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }
}
