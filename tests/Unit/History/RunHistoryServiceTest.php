<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Core\Clock;
use Sift\History\FileRunStore;
use Sift\History\RunHistoryService;
use Tests\Support\FixtureProject;

it('records a redacted normalized payload without mutating stdout payload', function (): void {
    $project = FixtureProject::create();
    $service = new RunHistoryService(
        storeFactory: static fn(HistoryConfig $config): FileRunStore => new FileRunStore($config->path()),
        clock: historyClock('2026-05-31T10:00:00+00:00'),
    );
    $payload = [
        'tool' => 'pest',
        'status' => 'passed',
        'summary' => ['tests' => 12],
        'items' => [],
        'artifacts' => [],
        'extra' => ['token' => 'secret-value'],
        'meta' => ['created_at' => '2026-05-31T09:59:59+00:00'],
    ];

    $record = historyRecorded($service->record($payload, historyServiceConfig($project)));

    expect($payload['extra']['token'])->toBe('secret-value');
    expect($record)->toMatchArray([
        'stored_at' => '2026-05-31T10:00:00+00:00',
        'created_at' => '2026-05-31T09:59:59+00:00',
        'tool' => 'pest',
        'status' => 'passed',
        'summary' => ['tests' => 12],
    ]);
    expect($record['run_id'] ?? null)->toMatch('/^run_[a-f0-9]{32}$/');

    $stored = historyRecorded((new FileRunStore($project->path('.sift/history')))->read(historyStringField($record, 'run_id')));
    $storedPayload = historyObjectField($stored, 'payload');
    $storedExtra = historyObjectField($storedPayload, 'extra');

    expect($storedExtra['token'] ?? null)->toBe('[REDACTED]');
});

it('truncates oversized payloads while keeping summary and status', function (): void {
    $project = FixtureProject::create();
    $service = new RunHistoryService(
        storeFactory: static fn(HistoryConfig $config): FileRunStore => new FileRunStore($config->path()),
        clock: historyClock('2026-05-31T10:00:00+00:00'),
    );
    $payload = [
        'tool' => 'phpstan',
        'status' => 'failed',
        'summary' => ['errors' => 5000],
        'items' => array_fill(0, 100, ['type' => 'issue', 'message' => str_repeat('x', 100)]),
        'artifacts' => [],
        'extra' => ['debug' => str_repeat('y', 1000)],
        'meta' => ['created_at' => '2026-05-31T09:59:59+00:00'],
    ];

    $record = historyRecorded($service->record($payload, historyServiceConfig($project, maxBytesPerRun: 1200)));
    $recordPayload = historyObjectField($record, 'payload');
    $recordMeta = historyObjectField($recordPayload, 'meta');

    expect($record['summary'])->toBe(['errors' => 5000]);
    expect($record['status'])->toBe('failed');
    expect($recordPayload['items'] ?? null)->toBe([]);
    expect($recordMeta['truncated'] ?? null)->toBeTrue();
});

/**
 * @param array<string, mixed>|null $record
 *
 * @return array<string, mixed>
 */
function historyRecorded(?array $record): array
{
    if ($record === null) {
        throw new RuntimeException('Expected history record.');
    }

    return $record;
}

/**
 * @param array<string, mixed> $record
 */
function historyStringField(array $record, string $key): string
{
    $value = $record[$key] ?? null;

    if (! is_string($value) || $value === '') {
        throw new RuntimeException(sprintf('Expected non-empty string field "%s".', $key));
    }

    return $value;
}

/**
 * @param array<string, mixed> $record
 *
 * @return array<string, mixed>
 */
function historyObjectField(array $record, string $key): array
{
    $value = $record[$key] ?? null;

    if (! is_array($value) || array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected object field "%s".', $key));
    }

    $object = [];

    foreach ($value as $field => $fieldValue) {
        if (is_string($field)) {
            $object[$field] = $fieldValue;
        }
    }

    return $object;
}

function historyServiceConfig(FixtureProject $project, int $maxBytesPerRun = 1048576): HistoryConfig
{
    return new HistoryConfig(
        enabled: true,
        path: $project->path('.sift/history'),
        maxFiles: 50,
        maxAgeDays: 30,
        maxBytesPerRun: $maxBytesPerRun,
        redactSecrets: true,
    );
}

function historyClock(string $now): Clock
{
    return new readonly class ($now) implements Clock {
        public function __construct(
            private string $now,
        ) {}

        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable($this->now);
        }

        public function monotonicSeconds(): float
        {
            return 0.0;
        }
    };
}
