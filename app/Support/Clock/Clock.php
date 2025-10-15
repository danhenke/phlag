<?php

declare(strict_types=1);

namespace Phlag\Support\Clock;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
