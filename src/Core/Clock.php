<?php

declare(strict_types=1);

namespace Sift\Core;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;

    public function monotonicSeconds(): float;
}
