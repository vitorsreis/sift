<?php

declare(strict_types=1);

namespace Sift\Config;

final readonly class HistoryConfig
{
    public function __construct(
        private bool $enabled,
        private string $path,
        private int $maxFiles,
        private ?int $maxAgeDays,
        private int $maxBytesPerRun,
        private bool $redactSecrets,
        private bool $defaultPath = false,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function maxFiles(): int
    {
        return $this->maxFiles;
    }

    public function maxAgeDays(): ?int
    {
        return $this->maxAgeDays;
    }

    public function maxBytesPerRun(): int
    {
        return $this->maxBytesPerRun;
    }

    public function redactSecrets(): bool
    {
        return $this->redactSecrets;
    }

    public function defaultPath(): bool
    {
        return $this->defaultPath;
    }
}
