<?php

declare(strict_types=1);

namespace Sift\Core;

final readonly class ExecutionResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public int $duration,
    ) {}
}
