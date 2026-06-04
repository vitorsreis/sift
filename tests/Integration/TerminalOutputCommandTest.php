<?php

declare(strict_types=1);

use Sift\History\FileRunStore;
use Tests\Support\CliRunner;
use Tests\Support\FixtureProject;

it('renders history list and view as terminal text by default', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));
    $store->store([
        'run_id' => '0td7j1a01z141z',
        'tool' => 'pest',
        'status' => 'passed',
        'summary' => ['tests' => 12],
        'items' => [['type' => 'test_failure', 'message' => 'failed']],
        'artifacts' => [],
        'extra' => [],
        'meta' => ['created_at' => '2026-05-31T10:00:00+00:00'],
    ]);

    $list = CliRunner::run(['--full', 'history', 'list'], $project->root());
    $view = CliRunner::run(['--full', 'history', 'view', '0td7j1a01z141z'], $project->root());

    expect($list['exit_code'])->toBe(0);
    expect($list['stderr'])->toBe('');
    expect($list['stdout'])->toContain('sift passed');
    expect($list['stdout'])->toContain('summary: total=1 returned=1 limit=20 offset=0');
    expect($list['stdout'])->toContain('0td7j1a01z141z');

    expect($view['exit_code'])->toBe(0);
    expect($view['stderr'])->toBe('');
    expect($view['stdout'])->toContain('pest passed');
    expect($view['stdout'])->toContain('summary: tests=12');
    expect($view['stdout'])->toContain('- test_failure failed');
});
