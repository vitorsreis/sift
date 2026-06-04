<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Console\CommandRoute;
use Sift\Console\Commands\HistoryClearCommand;
use Sift\Console\Commands\HistoryListCommand;
use Sift\Console\Commands\HistoryRemoveCommand;
use Sift\Console\Commands\HistoryViewCommand;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\History\FileRunStore;
use Tests\Support\FixtureProject;

it('lists history runs sorted by run id with limit offset and corrupt run entries', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));
    $store->store(historyCommandRun('0td7j1a01z141z', 'pest', '2026-05-31T10:00:00+00:00'));
    $store->store(historyCommandRun('0td7j1b0000001', 'phpunit', '2026-05-31T11:00:00+00:00'));

    $project->write('.sift/history/runs/sift_0td7j1c0zzzzzz_pest.json', '{');

    $payload = (new HistoryListCommand())->handle(
        new CommandRoute('history.list', options: ['limit' => 2, 'offset' => 0]),
        $project->root(),
    );

    expect($payload['summary'])->toMatchArray([
        'total' => 3,
        'returned' => 2,
        'limit' => 2,
        'offset' => 0,
    ]);
    expect(array_column(historyCommandItems($payload), 'run_id'))->toBe([
        '0td7j1c0zzzzzz',
        '0td7j1b0000001',
    ]);

    $defaultPayload = (new HistoryListCommand())->handle(new CommandRoute('history.list'), $project->root());

    expect($defaultPayload['summary'])->toMatchArray([
        'returned' => 3,
        'limit' => 20,
        'offset' => 0,
    ]);
    expect(historyCommandItems($defaultPayload)[0])->toMatchArray([
        'run_id' => '0td7j1c0zzzzzz',
        'type' => 'error',
        'status' => 'error',
    ]);
});

it('views a full run payload and individual payload sections', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));
    $store->store(historyCommandRun('0td7j1a01z141z', 'pest', '2026-05-31T10:00:00+00:00'));

    $command = new HistoryViewCommand();

    $full = $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z']), $project->root());
    $items = $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z', 'items']), $project->root());
    $summary = $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z', 'summary']), $project->root());
    $meta = $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z', 'meta']), $project->root());
    $artifacts = $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z', 'artifacts']), $project->root());
    $extra = $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z', 'extra']), $project->root());

    expect($full['tool'])->toBe('pest');
    expect($full['status'])->toBe('passed');
    expect($items['items'])->toBe([['type' => 'test_failure', 'message' => 'failed']]);
    expect($summary['summary'])->toBe(['tests' => 12]);
    expect($meta['meta'])->toBe(['created_at' => '2026-05-31T10:00:00+00:00']);
    expect($artifacts['artifacts'])->toBe([['path' => 'build/report.json']]);
    expect($extra['extra'])->toBe(['note' => 'ok']);
});

it('returns run_not_found for missing history view ids and rejects invalid sections', function (): void {
    $project = FixtureProject::create();
    $command = new HistoryViewCommand();

    expect(fn(): mixed => $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z']), $project->root()))
        ->toThrow(UserFacingException::class, 'History run was not found.');

    try {
        $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z']), $project->root());
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::RunNotFound);
    }

    $store = new FileRunStore($project->path('.sift/history'));
    $store->store(historyCommandRun('0td7j1a01z141z', 'pest', '2026-05-31T10:00:00+00:00'));

    expect(fn(): mixed => $command->handle(new CommandRoute('history.view', ['0td7j1a01z141z', 'bad']), $project->root()))
        ->toThrow(InvalidUsageException::class, 'Unsupported history section "bad".');
});

it('removes requested runs while reporting missing ids', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));
    $store->store(historyCommandRun('0td7j1a01z141z', 'pest', '2026-05-31T10:00:00+00:00'));
    $store->store(historyCommandRun('0td7j1b0000001', 'phpunit', '2026-05-31T11:00:00+00:00'));

    $payload = (new HistoryRemoveCommand())->handle(
        new CommandRoute('history.remove', [
            '0td7j1a01z141z',
            '0td7j1c0zzzzzz',
        ]),
        $project->root(),
    );

    expect($payload['summary'])->toBe(['removed' => 1, 'missing' => 1]);
    expect(array_column(historyCommandItems($payload), 'status'))->toBe(['removed', 'missing']);
    expect(is_file($project->path('.sift/history/runs/sift_0td7j1a01z141z_pest.json')))->toBeFalse();
    expect(is_file($project->path('.sift/history/runs/sift_0td7j1b0000001_phpunit.json')))->toBeTrue();
});

it('clears all history runs', function (): void {
    $project = FixtureProject::create();
    $store = new FileRunStore($project->path('.sift/history'));
    $store->store(historyCommandRun('0td7j1a01z141z', 'pest', '2026-05-31T10:00:00+00:00'));
    $store->store(historyCommandRun('0td7j1b0000001', 'phpunit', '2026-05-31T11:00:00+00:00'));

    $payload = (new HistoryClearCommand())->handle(new CommandRoute('history.clear'), $project->root());

    expect($payload['summary'])->toBe(['removed' => 2]);
    expect(is_dir($project->path('.sift/history')))->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function historyCommandRun(string $runId, string $tool, string $storedAt): array
{
    return [
        'run_id' => $runId,
        'tool' => $tool,
        'status' => 'passed',
        'summary' => ['tests' => 12],
        'items' => [['type' => 'test_failure', 'message' => 'failed']],
        'artifacts' => [['path' => 'build/report.json']],
        'extra' => ['note' => 'ok'],
        'meta' => [
            'created_at' => $storedAt,
        ],
    ];
}

/**
 * @param array<string, mixed> $payload
 *
 * @return list<array<string, mixed>>
 */
function historyCommandItems(array $payload): array
{
    $items = $payload['items'] ?? null;

    if (! is_array($items) || ! array_is_list($items)) {
        throw new RuntimeException('Expected history command items list.');
    }

    $normalized = [];

    foreach ($items as $item) {
        if (! is_array($item) || array_is_list($item)) {
            throw new RuntimeException('Expected history command item objects.');
        }

        $object = [];

        foreach ($item as $key => $value) {
            if (is_string($key)) {
                $object[$key] = $value;
            }
        }

        $normalized[] = $object;
    }

    return $normalized;
}

function historyCommandConfig(FixtureProject $project): HistoryConfig
{
    return new HistoryConfig(
        enabled: true,
        path: $project->path('.sift/history'),
        maxFiles: 50,
        maxAgeDays: 30,
        maxBytesPerRun: 1048576,
        redactSecrets: true,
    );
}
