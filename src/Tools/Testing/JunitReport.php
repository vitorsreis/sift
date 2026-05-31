<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

final readonly class JunitReport
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private int $tests,
        private int $failures,
        private int $errors,
        private int $skipped,
        private array $items,
    ) {}

    /**
     * @return array{tests: int, passed: int, failures: int, errors: int, skipped: int}
     */
    public function summary(): array
    {
        return [
            'tests' => $this->tests,
            'passed' => max(0, $this->tests - $this->failures - $this->errors - $this->skipped),
            'failures' => $this->failures,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }
}
