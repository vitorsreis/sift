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
        historyRetentionRun('0tfwhc001z141z', 'pest'),
        historyRetentionRun('0tfumo00000001', 'pest'),
        historyRetentionRun('0tfss000zzzzzz', 'pest'),
        historyRetentionRun('0tctdc00abcdef', 'phpunit'),
        ['run_id' => 'corrupt', 'type' => 'error', 'status' => 'error'],
    ], $config, new DateTimeImmutable('2026-05-31T12:00:00+00:00'));

    expect($removals)->toBe([
        '0tfss000zzzzzz',
        '0tctdc00abcdef',
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
        historyRetentionRun('0tctdc00abcdef', 'pest'),
    ], $config, new DateTimeImmutable('2026-05-31T12:00:00+00:00'));

    expect($removals)->toBe([]);
});

/**
 * @return array<string, mixed>
 */
function historyRetentionRun(string $runId, string $tool): array
{
    return [
        'run_id' => $runId,
        'tool' => $tool,
        'status' => 'passed',
    ];
}
