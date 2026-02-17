<?php

declare(strict_types=1);

use Sift\Core\NormalizedResult;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\FileRunStore;
use Sift\Runtime\ViewService;

it('persists runs and exposes list and scoped views', function (): void {
    $cwd = makeTempDirectory();
    $store = new FileRunStore;
    $service = new ViewService($store);

    try {
        $runId = $store->put($cwd, new NormalizedResult(
            tool: 'pint',
            status: 'failed',
            summary: ['files' => 1],
            items: [['file' => 'Bad.php', 'fixers' => ['class_definition']]],
            meta: ['created_at' => '2026-02-11T08:17:44+00:00'],
        ));

        $listing = $service->list($cwd, 10, 0);
        $summary = $service->view($cwd, $runId, 'summary', 10, 0);
        $items = $service->view($cwd, $runId, 'items', 10, 0);

        expect($listing['total'])->toBe(1)
            ->and($listing['items'][0]['run_id'])->toBe($runId)
            ->and($summary)->toBe([
                'status' => 'failed',
                'summary' => ['files' => 1],
                'run_id' => $runId,
            ])
            ->and($items['items'])->toBe([['file' => 'Bad.php', 'fixers' => ['class_definition']]]);
    } finally {
        removeDirectory($cwd);
    }
});

it('clears history directories', function (): void {
    $cwd = makeTempDirectory();
    $store = new FileRunStore;
    $service = new ViewService($store);

    try {
        $store->put($cwd, new NormalizedResult(
            tool: 'pint',
            status: 'passed',
            meta: ['created_at' => '2026-02-11T08:17:44+00:00'],
        ));

        $cleared = $service->clear($cwd);

        expect($cleared['status'])->toBe('cleared')
            ->and($cleared['deleted'])->toBe(1)
            ->and($service->list($cwd, 10, 0)['total'])->toBe(0);
    } finally {
        removeDirectory($cwd);
    }
});

it('exposes fuller scopes and reports missing runs', function (): void {
    $cwd = makeTempDirectory();
    $store = new FileRunStore;
    $service = new ViewService($store);

    try {
        $runId = $store->put($cwd, new NormalizedResult(
            tool: 'pest',
            status: 'failed',
            items: [['type' => 'failure', 'test' => 'it fails', 'file' => 'tests/FailingTest.php', 'line' => 6]],
            artifacts: [['path' => 'build/coverage/clover.xml']],
            extra: ['coverage' => ['coverage_percent' => 79.5]],
            meta: ['created_at' => '2026-02-16T08:47:58+00:00', 'coverage' => true],
        ));

        $meta = $service->view($cwd, $runId, 'meta', 10, 0);
        $artifacts = $service->view($cwd, $runId, 'artifacts', 10, 0);
        $extra = $service->view($cwd, $runId, 'extra', 10, 0);

        expect($meta)->toBe([
            'status' => 'failed',
            'meta' => ['created_at' => '2026-02-16T08:47:58+00:00', 'coverage' => true],
            'run_id' => $runId,
        ])
            ->and($artifacts)->toBe([
                'status' => 'failed',
                'scope' => 'artifacts',
                'offset' => 0,
                'limit' => 10,
                'count' => 1,
                'total' => 1,
                'items' => [['path' => 'build/coverage/clover.xml']],
                'run_id' => $runId,
            ])
            ->and($extra)->toBe([
                'status' => 'failed',
                'extra' => ['coverage' => ['coverage_percent' => 79.5]],
                'run_id' => $runId,
            ]);

        expect(fn () => $service->view($cwd, 'missing-run', 'summary', 10, 0))
            ->toThrow(UserFacingException::class);
    } finally {
        removeDirectory($cwd);
    }
});
