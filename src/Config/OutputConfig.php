<?php

declare(strict_types=1);

namespace Sift\Config;

final readonly class OutputConfig
{
    public function __construct(
        private string $size,
        private bool $pretty,
        private bool $showProcess,
        private string $format = 'terminal',
        private bool $colored = true,
    ) {}

    public function format(): string
    {
        return $this->format;
    }

    public function size(): string
    {
        return $this->size;
    }

    public function pretty(): bool
    {
        return $this->pretty;
    }

    public function showProcess(): bool
    {
        return $this->showProcess;
    }

    public function colored(): bool
    {
        return $this->colored;
    }
}
