<?php

declare(strict_types=1);

namespace Sift\Execution;

final readonly class Platform
{
    public function __construct(
        private string $family = PHP_OS_FAMILY,
    ) {}

    public function isWindows(): bool
    {
        return $this->family === 'Windows';
    }
}
