<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

final readonly class CloverCoverageReport
{
    /**
     * @param list<array{file: string, percent: float}> $files
     */
    public function __construct(
        private float $coveragePercent,
        private ?float $minimum,
        private bool $thresholdFailed,
        private array $files,
    ) {}

    /**
     * @return array<string, float|int>
     */
    public function summary(): array
    {
        $summary = [
            'coverage_percent' => $this->coveragePercent,
        ];

        if ($this->minimum !== null) {
            $summary['coverage_min'] = $this->minimum;
            $summary['coverage_files_below_min'] = count($this->files);
        }

        return $summary;
    }

    public function thresholdFailed(): bool
    {
        return $this->thresholdFailed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->files as $entry) {
            $items[] = [
                'type' => 'coverage',
                'file' => $entry['file'],
                'percent' => $entry['percent'],
            ];
        }

        return $items;
    }
}
