<?php

declare(strict_types=1);

namespace Sift\Console;

final readonly class OutputPreferences
{
    public function __construct(
        private OutputSize $size,
        private bool $pretty,
        private bool $showProcess,
        private bool $debug,
        private OutputFormat $format = OutputFormat::Terminal,
    ) {}

    public function size(): OutputSize
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

    public function debug(): bool
    {
        return $this->debug;
    }

    public function format(): OutputFormat
    {
        return $this->format;
    }
}
