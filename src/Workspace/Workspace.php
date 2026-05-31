<?php

declare(strict_types=1);

namespace Sift\Workspace;

final readonly class Workspace
{
    public function __construct(
        private string $cwd,
        private string $projectRoot,
        private ?string $configPath,
        private bool $projectDetected,
        private string $globalRoot,
    ) {}

    public function cwd(): string
    {
        return $this->cwd;
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function configPath(): ?string
    {
        return $this->configPath;
    }

    public function projectDetected(): bool
    {
        return $this->projectDetected;
    }

    public function globalRoot(): string
    {
        return $this->globalRoot;
    }
}
