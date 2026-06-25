<?php

declare(strict_types=1);

use Sift\Config\ConfigDefaults;
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
    $listStdout = stripSiftAnsi($list['stdout']);
    $viewStdout = stripSiftAnsi($view['stdout']);

    expect($list['exit_code'])->toBe(0);
    expect($list['stderr'])->toBe('');
    expect($listStdout)->toContain('sift passed');
    expect($listStdout)->toContain('summary: total=1 returned=1 limit=20 offset=0');
    expect($listStdout)->toContain('0td7j1a01z141z');

    expect($view['exit_code'])->toBe(0);
    expect($view['stderr'])->toBe('');
    expect($viewStdout)->toContain('pest passed');
    expect($viewStdout)->toContain('summary: tests=12');
    expect($viewStdout)->toContain('- test_failure failed');
});

it('does not ignore invalid config for history commands', function (): void {
    $project = FixtureProject::create();
    $project->writeJson('sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'history' => [
            'max_files' => 0,
        ],
    ]);

    $result = CliRunner::run(['--json', '--no-pretty', 'history', 'list'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = $payload['error'] ?? null;

    if (! is_array($error)) {
        throw new RuntimeException('Expected error payload.');
    }

    expect($result['exit_code'])->toBe(3);
    expect($result['stdout'])->toBe('');
    expect($error['code'] ?? null)->toBe('invalid_config');
});
