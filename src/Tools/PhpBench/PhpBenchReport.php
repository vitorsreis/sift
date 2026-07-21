<?php

declare(strict_types=1);

namespace Sift\Tools\PhpBench;

final readonly class PhpBenchReport
{
    /**
     * @param array{benchmarks: int, subjects: int, variants: int, iterations: int, failures: int, errors: int} $summary
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $summary,
        private array $items,
    ) {}

    /**
     * @return array{benchmarks: int, subjects: int, variants: int, iterations: int, failures: int, errors: int}
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
        return $this->summary['failures'] + $this->summary['errors'];
    }

    public function errors(): int
    {
        return $this->summary['errors'];
    }
}
