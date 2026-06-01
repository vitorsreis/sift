<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\History\HistoryRetentionPolicy;

it('calculates removals by max files per tool and max age', function (): void {
    $policy = new HistoryRetentionPolicy();
    $config = new HistoryConfig(
        enabled: true,
        path: '.sift/history',
        maxFiles: 2,
        maxAgeDays: 30,
        maxBytesPerRun: 1048576,
        redactSecrets: true,
    );

    $removals = $policy->expiredRunIds([
        historyRetentionRun('run_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'pest', '2026-05-31T12:00:00+00:00'),
        historyRetentionRun('run_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'pest', '2026-05-30T12:00:00+00:00'),
        historyRetentionRun('run_cccccccccccccccccccccccccccccccc', 'pest', '2026-05-29T12:00:00+00:00'),
        historyRetentionRun('run_dddddddddddddddddddddddddddddddd', 'phpunit', '2026-04-01T12:00:00+00:00'),
        ['run_id' => 'run_corrupt', 'type' => 'error', 'status' => 'error'],
    ], $config, new DateTimeImmutable('2026-05-31T12:00:00+00:00'));

    expect($removals)->toBe([
        'run_cccccccccccccccccccccccccccccccc',
        'run_dddddddddddddddddddddddddddddddd',
    ]);
});

/**
 * @return array<string, mixed>
 */
function historyRetentionRun(string $runId, string $tool, string $storedAt): array
{
    return [
        'run_id' => $runId,
        'tool' => $tool,
        'stored_at' => $storedAt,
        'status' => 'passed',
    ];
}
