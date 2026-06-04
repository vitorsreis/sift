<?php

declare(strict_types=1);

namespace Sift\History;

use DateTimeImmutable;
use LogicException;
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
                static fn(array $left, array $right): int => strcmp($right['run_id'], $left['run_id']),
            );

            foreach (array_slice($toolRuns, $config->maxFiles()) as $run) {
                $removals[] = $run['run_id'];
            }
        }

        $maxAgeDays = $config->maxAgeDays();

        if ($maxAgeDays !== null) {
            $oldestAllowed = $now->modify(sprintf('-%d days', $maxAgeDays));

            foreach ($eligibleRuns as $run) {
                if ($this->createdAt($run['run_id']) < $oldestAllowed) {
                    $removals[] = $run['run_id'];
                }
            }
        }

        return array_values(array_unique($removals));
    }

    /**
     * @param list<array<string, mixed>> $runs
     *
     * @return list<array{run_id: string, tool: string}>
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
            if (! is_string($runId)) {
                continue;
            }

            if (! RunIdFormat::createdAt($runId) instanceof DateTimeImmutable) {
                continue;
            }

            if (! is_string($tool)) {
                continue;
            }

            $eligible[] = [
                'run_id' => $runId,
                'tool' => $tool,
            ];
        }

        return $eligible;
    }

    /**
     * @param list<array{run_id: string, tool: string}> $runs
     *
     * @return array<string, list<array{run_id: string, tool: string}>>
     */
    private function byTool(array $runs): array
    {
        $byTool = [];

        foreach ($runs as $run) {
            $byTool[$run['tool']][] = $run;
        }

        return $byTool;
    }

    private function createdAt(string $runId): DateTimeImmutable
    {
        $createdAt = RunIdFormat::createdAt($runId);

        if (! $createdAt instanceof DateTimeImmutable) {
            throw new LogicException('Eligible history run id must encode creation time.');
        }

        return $createdAt;
    }
}
