<?php

declare(strict_types=1);

use Sift\Core\NormalizedResult;
use Sift\Runtime\FileRunStore;

it('stores runs in a custom history path and rotates old files', function (): void {
    $cwd = makeTempDirectory();
    $store = new FileRunStore;
    $history = [
        'enabled' => true,
        'max_files' => 2,
        'path' => 'var/history',
    ];

    try {
        $firstRunId = $store->put($cwd, new NormalizedResult(
            tool: 'pint',
            status: 'failed',
            meta: ['created_at' => '2026-02-13T20:14:22-03:00'],
        ), $history);
        $secondRunId = $store->put($cwd, new NormalizedResult(
            tool: 'pint',
            status: 'failed',
            meta: ['created_at' => '2026-02-13T20:39:17-03:00'],
        ), $history);
        $thirdRunId = $store->put($cwd, new NormalizedResult(
            tool: 'pint',
            status: 'passed',
            meta: ['created_at' => '2026-02-13T21:08:53-03:00'],
        ), $history);

        $listing = $store->list($cwd, 10, 0, $history);
        $files = glob($cwd.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'history'.DIRECTORY_SEPARATOR.'*.json');

        expect($listing['total'])->toBe(2)
            ->and($listing['items'])->toHaveCount(2)
            ->and(array_column($listing['items'], 'run_id'))->toBe([$thirdRunId, $secondRunId])
            ->and($store->get($cwd, $firstRunId, $history))->toBeNull()
            ->and($store->get($cwd, $secondRunId, $history))->not->toBeNull()
            ->and($files)->toHaveCount(2);
    } finally {
        removeDirectory($cwd);
    }
});

it('keeps the most recently written run when created_at timestamps collide', function (): void {
    $cwd = makeTempDirectory();
    $store = new FileRunStore;
    $history = [
        'enabled' => true,
        'max_files' => 1,
        'path' => 'var/history',
    ];

    try {
        $firstRunId = $store->put($cwd, new NormalizedResult(
            tool: 'pint',
            status: 'passed',
            meta: ['created_at' => '2026-02-13T21:36:41-03:00'],
        ), $history);
        $secondRunId = $store->put($cwd, new NormalizedResult(
            tool: 'pint',
            status: 'passed',
            meta: ['created_at' => '2026-02-13T21:36:41-03:00'],
        ), $history);

        $listing = $store->list($cwd, 10, 0, $history);

        expect($listing['total'])->toBe(1)
            ->and(array_column($listing['items'], 'run_id'))->toBe([$secondRunId])
            ->and($store->get($cwd, $firstRunId, $history))->toBeNull()
            ->and($store->get($cwd, $secondRunId, $history))->not->toBeNull();
    } finally {
        removeDirectory($cwd);
    }
});
