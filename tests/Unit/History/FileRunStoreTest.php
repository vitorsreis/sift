<?php

declare(strict_types=1);

use Sift\History\FileRunStore;
use Tests\Support\FixtureProject;

it('stores one normalized run per json file and reads it back', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));

    $store->store(historyRunDocument('run_11111111111111111111111111111111', 'pest'));

    $document = $project->readJson('.sift/history/runs/run_11111111111111111111111111111111.json');

    expect($document)->toMatchArray([
        'run_id' => 'run_11111111111111111111111111111111',
        'tool' => 'pest',
        'status' => 'passed',
        'summary' => ['tests' => 12],
    ]);
    expect($store->read('run_11111111111111111111111111111111'))->toMatchArray($document);
});

it('lists valid runs and keeps corrupted runs visible as error items', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));

    $store->store(historyRunDocument('run_11111111111111111111111111111111', 'pest'));

    $project->write('.sift/history/runs/run_22222222222222222222222222222222.json', '{');

    $runs = $store->list();

    expect($runs)->toHaveCount(2);
    expect($runs[0]['run_id'] ?? null)->toBe('run_11111111111111111111111111111111');
    expect($runs[1])->toMatchArray([
        'run_id' => 'run_22222222222222222222222222222222',
        'type' => 'error',
        'status' => 'error',
    ]);
});

it('removes only requested runs and clears the history directory', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));

    $store->store(historyRunDocument('run_11111111111111111111111111111111', 'pest'));
    $store->store(historyRunDocument('run_22222222222222222222222222222222', 'phpunit'));

    expect($store->remove('run_11111111111111111111111111111111'))->toBeTrue();
    expect($store->remove('run_33333333333333333333333333333333'))->toBeFalse();
    expect(is_file($project->path('.sift/history/runs/run_11111111111111111111111111111111.json')))->toBeFalse();
    expect(is_file($project->path('.sift/history/runs/run_22222222222222222222222222222222.json')))->toBeTrue();

    expect($store->clearAll())->toBe(1);
    expect(is_dir($project->path('.sift/history')))->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function historyRunDocument(string $runId, string $tool): array
{
    return [
        'run_id' => $runId,
        'stored_at' => '2026-05-31T10:00:00+00:00',
        'created_at' => '2026-05-31T09:59:59+00:00',
        'tool' => $tool,
        'status' => 'passed',
        'summary' => ['tests' => 12],
        'payload' => [
            'tool' => $tool,
            'status' => 'passed',
            'summary' => ['tests' => 12],
            'items' => [],
            'artifacts' => [],
            'extra' => [],
            'meta' => ['created_at' => '2026-05-31T09:59:59+00:00'],
        ],
    ];
}
