<?php

declare(strict_types=1);

namespace Phlag\Auth\Jwt;

use InvalidArgumentException;

final class Secret
{
    public function __construct(private readonly string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('The JWT secret must not be empty.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
