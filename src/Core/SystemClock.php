<?php

declare(strict_types=1);

namespace Sift\Core;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function monotonicSeconds(): float
    {
        return (float) hrtime(true) / 1_000_000_000;
    }
}
