<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCsFixer;

final readonly class PhpCsFixerReport
{
    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $summary,
        private array $items,
        private int $files,
        private int $fixers,
        private int $diffs,
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

    public function files(): int
    {
        return $this->files;
    }

    public function fixers(): int
    {
        return $this->fixers;
    }

    public function diffs(): int
    {
        return $this->diffs;
    }
}
