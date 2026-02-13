<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Runtime\ResultMetaStamper;

it('fills missing exit code, duration, and created at meta fields', function (): void {
    $stamped = (new ResultMetaStamper)->stamp(
        new NormalizedResult(
            tool: 'demo',
            status: 'passed',
            meta: [],
        ),
        new ExecutionResult(7, '', '', 123),
    );

    expect($stamped->meta['exit_code'])->toBe(7)
        ->and($stamped->meta['duration'])->toBe(123)
        ->and($stamped->meta['created_at'])->toBeString();
});

it('preserves explicit meta fields provided by adapters', function (): void {
    $stamped = (new ResultMetaStamper)->stamp(
        new NormalizedResult(
            tool: 'demo',
            status: 'failed',
            meta: [
                'exit_code' => 2,
                'duration' => 456,
                'created_at' => '2026-02-13T08:00:00+00:00',
            ],
        ),
        new ExecutionResult(7, '', '', 123),
    );

    expect($stamped->meta)->toBe([
        'exit_code' => 2,
        'duration' => 456,
        'created_at' => '2026-02-13T08:00:00+00:00',
    ]);
});
