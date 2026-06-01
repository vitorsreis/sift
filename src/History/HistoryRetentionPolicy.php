<?php

declare(strict_types=1);

namespace Sift\History;

use DateTimeImmutable;
use Sift\Config\HistoryConfig;

final readonly class HistoryRetentionPolicy
{
    /**
     * @param list<array<string, mixed>> $runs
     *
     * @return list<string>
     */
    public function expiredRunIds(array $runs, HistoryConfig $config, DateTimeImmutable $now): array
    {
        $eligibleRuns = $this->eligibleRuns($runs);
        $removals = [];

        foreach ($this->byTool($eligibleRuns) as $toolRuns) {
            usort(
                $toolRuns,
                static fn(array $left, array $right): int => strcmp($right['stored_at'], $left['stored_at']),
            );

            foreach (array_slice($toolRuns, $config->maxFiles()) as $run) {
                $removals[] = $run['run_id'];
            }
        }

        $maxAgeDays = $config->maxAgeDays();

        if ($maxAgeDays !== null) {
            $oldestAllowed = $now->modify(sprintf('-%d days', $maxAgeDays));

            foreach ($eligibleRuns as $run) {
                if ($this->storedAt($run['stored_at']) < $oldestAllowed) {
                    $removals[] = $run['run_id'];
                }
            }
        }

        return array_values(array_unique($removals));
    }

    /**
     * @param list<array<string, mixed>> $runs
     *
     * @return list<array{run_id: string, tool: string, stored_at: string}>
     */
    private function eligibleRuns(array $runs): array
    {
        $eligible = [];

        foreach ($runs as $run) {
            if (($run['type'] ?? null) === 'error') {
                continue;
            }

            $runId = $run['run_id'] ?? null;
            $tool = $run['tool'] ?? null;
            $storedAt = $run['stored_at'] ?? null;
            if (! is_string($runId)) {
                continue;
            }

            if (! is_string($tool)) {
                continue;
            }

            if (! is_string($storedAt)) {
                continue;
            }

            $eligible[] = [
                'run_id' => $runId,
                'tool' => $tool,
                'stored_at' => $storedAt,
            ];
        }

        return $eligible;
    }

    /**
     * @param list<array{run_id: string, tool: string, stored_at: string}> $runs
     *
     * @return array<string, list<array{run_id: string, tool: string, stored_at: string}>>
     */
    private function byTool(array $runs): array
    {
        $byTool = [];

        foreach ($runs as $run) {
            $byTool[$run['tool']][] = $run;
        }

        return $byTool;
    }

    private function storedAt(string $storedAt): DateTimeImmutable
    {
        return new DateTimeImmutable($storedAt);
    }
}
