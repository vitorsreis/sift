<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use Sift\Core\Clock;

final class InMemoryClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        private float $monotonicSeconds = 0.0,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function monotonicSeconds(): float
    {
        return $this->monotonicSeconds;
    }

    public function advance(float $seconds): void
    {
        $this->monotonicSeconds += $seconds;
        $this->now = $this->now->modify(sprintf('+%d microseconds', (int) round($seconds * 1_000_000)));
    }
}
