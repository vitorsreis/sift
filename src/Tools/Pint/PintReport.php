<?php

declare(strict_types=1);

namespace Sift\Tools\Pint;

final readonly class PintReport
{
    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $summary,
        private array $items,
        private string $result,
        private int $files,
        private int $fixers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function result(): string
    {
        return $this->result;
    }

    public function files(): int
    {
        return $this->files;
    }

    public function fixers(): int
    {
        return $this->fixers;
    }

    public function changed(): bool
    {
        return $this->result === 'fixed' && $this->files > 0;
    }

    public function findings(): int
    {
        return $this->changed() ? 0 : $this->files;
    }
}
