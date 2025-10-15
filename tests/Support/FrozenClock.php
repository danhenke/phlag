<?php

declare(strict_types=1);

namespace Tests\Support;

use DateInterval;
use DateTimeImmutable;
use Phlag\Support\Clock\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public static function fromTimestamp(int $timestamp): self
    {
        return new self((new DateTimeImmutable)->setTimestamp($timestamp));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceSeconds(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->now = $this->now->add(new DateInterval('PT'.(string) $seconds.'S'));
    }
}
