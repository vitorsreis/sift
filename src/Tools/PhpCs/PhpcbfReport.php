<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCs;

final readonly class PhpcbfReport
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
        private int $fixed,
        private int $remaining,
        private bool $recognized,
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

    public function fixed(): int
    {
        return $this->fixed;
    }

    public function remaining(): int
    {
        return $this->remaining;
    }

    public function recognized(): bool
    {
        return $this->recognized;
    }

    public function changed(): bool
    {
        return $this->fixed > 0;
    }
}
