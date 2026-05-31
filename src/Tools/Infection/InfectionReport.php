<?php

declare(strict_types=1);

namespace Sift\Tools\Infection;

final readonly class InfectionReport
{
    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $summary,
        private array $items,
        private float $msi,
        private float $coveredMsi,
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

    public function msi(): float
    {
        return $this->msi;
    }

    public function coveredMsi(): float
    {
        return $this->coveredMsi;
    }
}
