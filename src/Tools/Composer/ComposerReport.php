<?php

declare(strict_types=1);

namespace Sift\Tools\Composer;

final readonly class ComposerReport
{
    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private array $summary,
        private array $items,
        private int $findings = 0,
        private array $extra = [],
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

    /**
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }
}
