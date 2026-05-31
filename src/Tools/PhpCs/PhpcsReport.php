<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCs;

final readonly class PhpcsReport
{
    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $summary,
        private array $items,
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

    public function findings(): int
    {
        return $this->findings;
    }
}
