<?php

declare(strict_types=1);

namespace Sift\Tools\Rector;

final readonly class RectorReport
{
    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $summary,
        private array $items,
        private int $changedFiles,
        private int $errors,
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

    public function changedFiles(): int
    {
        return $this->changedFiles;
    }

    public function errors(): int
    {
        return $this->errors;
    }

    public function findings(): int
    {
        return $this->changedFiles + $this->errors;
    }
}
