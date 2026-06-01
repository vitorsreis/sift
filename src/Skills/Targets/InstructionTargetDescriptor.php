<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

final readonly class InstructionTargetDescriptor
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        private string $name,
        private string $relativePath,
        private array $aliases = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    public function matches(string $target): bool
    {
        return $target === $this->name || in_array($target, $this->aliases, true);
    }
}
