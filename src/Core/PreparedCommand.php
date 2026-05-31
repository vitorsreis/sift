<?php

declare(strict_types=1);

namespace Sift\Core;

use InvalidArgumentException;

final readonly class PreparedCommand
{
    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment
     */
    public function __construct(
        private string $tool,
        private string $binary,
        private array $arguments = [],
        private string $cwd = '.',
        private array $environment = [],
        private int $timeout = 0,
    ) {
        if (trim($tool) === '') {
            throw new InvalidArgumentException('Prepared command tool cannot be empty.');
        }

        if (trim($binary) === '') {
            throw new InvalidArgumentException('Prepared command binary cannot be empty.');
        }

        if ($timeout < 0) {
            throw new InvalidArgumentException('Prepared command timeout cannot be negative.');
        }
    }

    public function tool(): string
    {
        return $this->tool;
    }

    public function binary(): string
    {
        return $this->binary;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return non-empty-list<string>
     */
    public function argv(): array
    {
        return [$this->binary, ...$this->arguments];
    }

    public function cwd(): string
    {
        return $this->cwd;
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return $this->environment;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }
}
