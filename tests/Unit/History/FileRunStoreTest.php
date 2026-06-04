<?php

declare(strict_types=1);

use Sift\History\FileRunStore;
use Tests\Support\FixtureProject;

it('stores one normalized run per json file and reads it back', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));

    $store->store(historyRunDocument('0td7j1a01z141z', 'pest'));

    $document = $project->readJson('.sift/history/runs/sift_0td7j1a01z141z_pest.json');

    expect($document['run_id'] ?? null)->toBe('0td7j1a01z141z');
    expect($document)->not->toHaveKeys(['payload', 'stored_at']);
    expect($document['tool'] ?? null)->toBe('pest');
    expect($document['status'] ?? null)->toBe('passed');
    expect($document['summary'] ?? null)->toBe(['tests' => 12]);
    expect($store->read('0td7j1a01z141z'))->toMatchArray($document);
});

it('lists valid runs and keeps corrupted runs visible as error items', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));

    $store->store(historyRunDocument('0td7j1a01z141z', 'pest'));

    $project->write('.sift/history/runs/sift_0td7j1b0000001_pest.json', '{');

    $runs = $store->list();

    expect($runs)->toHaveCount(2);
    expect($runs[0]['run_id'] ?? null)->toBe('0td7j1a01z141z');
    expect($runs[1])->toMatchArray([
        'run_id' => '0td7j1b0000001',
        'type' => 'error',
        'status' => 'error',
    ]);
});

it('removes only requested runs and clears the history directory', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));

    $store->store(historyRunDocument('0td7j1a01z141z', 'pest'));
    $store->store(historyRunDocument('0td7j1b0000001', 'phpunit'));

    expect($store->remove('0td7j1a01z141z'))->toBeTrue();
    expect($store->remove('0td7j1c0zzzzzz'))->toBeFalse();
    expect(is_file($project->path('.sift/history/runs/sift_0td7j1a01z141z_pest.json')))->toBeFalse();
    expect(is_file($project->path('.sift/history/runs/sift_0td7j1b0000001_phpunit.json')))->toBeTrue();

    expect($store->clearAll())->toBe(1);
    expect(is_dir($project->path('.sift/history')))->toBeFalse();
});

it('removes only the empty default sift parent during clear', function (): void {
    $project = FixtureProject::create();
    $defaultStore = new FileRunStore($project->path('.sift/history'), removeDefaultParentOnClear: true);
    $customStore = new FileRunStore($project->path('storage/history'));
    $customSiftStore = new FileRunStore($project->path('nested/.sift/history'));

    $defaultStore->store(historyRunDocument('0td7j1a01z141z', 'pest'));
    $customStore->store(historyRunDocument('0td7j1b0000001', 'pest'));
    $customSiftStore->store(historyRunDocument('0td7j1c0zzzzzz', 'pest'));

    expect($defaultStore->clearAll())->toBe(1);
    expect($customStore->clearAll())->toBe(1);
    expect($customSiftStore->clearAll())->toBe(1);

    expect(is_dir($project->path('.sift')))->toBeFalse();
    expect(is_dir($project->path('storage')))->toBeTrue();
    expect(is_dir($project->path('nested/.sift')))->toBeTrue();
});

/**
 * @return array<string, mixed>
 */
function historyRunDocument(string $runId, string $tool): array
{
    return [
        'run_id' => $runId,
        'tool' => $tool,
        'status' => 'passed',
        'summary' => ['tests' => 12],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'created_at' => '2026-05-31T09:59:59+00:00',
        ],
    ];
}
