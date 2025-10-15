<?php

declare(strict_types=1);

namespace Phlag\Auth\Jwt;

use InvalidArgumentException;

final class PublicKey
{
    public function __construct(
        private readonly string $keyId,
        private readonly string $publicKey,
    ) {
        if ($keyId === '') {
            throw new InvalidArgumentException('The JWT key id must not be empty.');
        }

        if ($publicKey === '') {
            throw new InvalidArgumentException('The JWT public key must not be empty.');
        }
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }
}
