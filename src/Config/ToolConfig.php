<?php

declare(strict_types=1);

namespace Sift\Config;

final readonly class ToolConfig
{
    /**
     * @param list<string> $blockedArgs
     */
    public function __construct(
        private string $name,
        private bool $enabled,
        private ?string $binary,
        private array $blockedArgs,
        private int $timeout,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function binary(): ?string
    {
        return $this->binary;
    }

    /**
     * @return list<string>
     */
    public function blockedArgs(): array
    {
        return $this->blockedArgs;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }
}
