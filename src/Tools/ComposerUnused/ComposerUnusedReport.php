<?php

declare(strict_types=1);

namespace Sift\Tools\ComposerUnused;

final readonly class ComposerUnusedReport
{
    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $summary,
        private array $items,
        private int $unusedPackages,
        private int $findings,
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

    public function unusedPackages(): int
    {
        return $this->unusedPackages;
    }

    public function findings(): int
    {
        return $this->findings;
    }
}
