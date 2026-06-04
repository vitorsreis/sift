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
        historyRetentionRun('0td7j1a01z141z', 'pest', '2026-05-31T12:00:00+00:00'),
        historyRetentionRun('0td7j1b0000001', 'pest', '2026-05-30T12:00:00+00:00'),
        historyRetentionRun('0td7j1c0zzzzzz', 'pest', '2026-05-29T12:00:00+00:00'),
        historyRetentionRun('0td7j1d0abcdef', 'phpunit', '2026-04-01T12:00:00+00:00'),
        ['run_id' => 'corrupt', 'type' => 'error', 'status' => 'error'],
    ], $config, new DateTimeImmutable('2026-05-31T12:00:00+00:00'));

    expect($removals)->toBe([
        '0td7j1c0zzzzzz',
        '0td7j1d0abcdef',
    ]);
});

it('skips age retention when max age days is absent', function (): void {
    $policy = new HistoryRetentionPolicy();
    $config = new HistoryConfig(
        enabled: true,
        path: '.sift/history',
        maxFiles: 10,
        maxAgeDays: null,
        maxBytesPerRun: 1048576,
        redactSecrets: true,
    );

    $removals = $policy->expiredRunIds([
        historyRetentionRun('0td7j1a01z141z', 'pest', '2026-04-01T12:00:00+00:00'),
    ], $config, new DateTimeImmutable('2026-05-31T12:00:00+00:00'));

    expect($removals)->toBe([]);
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
