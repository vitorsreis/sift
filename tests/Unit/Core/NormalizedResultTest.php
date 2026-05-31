<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;

it('serializes the normalized result payload shape', function (): void {
    $result = new NormalizedResult(
        tool: 'pest',
        status: RunStatus::Failed,
        summary: ['tests' => 12, 'failures' => 1],
        items: [
            ['type' => ItemType::TestFailure->value, 'message' => 'Expected true to be false.'],
        ],
        artifacts: [
            ['path' => 'build/junit.xml', 'kind' => 'junit'],
        ],
        extra: ['raw_exit_code' => 1],
        meta: ['subcommand' => 'pest'],
    );

    expect($result->toPayload())->toBe([
        'tool' => 'pest',
        'status' => 'failed',
        'summary' => ['tests' => 12, 'failures' => 1],
        'items' => [
            ['type' => 'test_failure', 'message' => 'Expected true to be false.'],
        ],
        'artifacts' => [
            ['path' => 'build/junit.xml', 'kind' => 'junit'],
        ],
        'extra' => ['raw_exit_code' => 1],
        'meta' => ['subcommand' => 'pest'],
    ]);
});

it('defaults optional payload collections to empty arrays', function (): void {
    $result = NormalizedResult::passed('sift', ['version' => '2.0.0']);

    expect($result->toPayload())->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['version' => '2.0.0'],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => [],
    ]);
});

it('rejects item types outside the catalog', function (): void {
    expect(fn(): NormalizedResult => new NormalizedResult(
        tool: 'custom',
        status: RunStatus::Passed,
        items: [
            ['type' => 'command', 'name' => 'help'],
        ],
    ))->toThrow(InvalidArgumentException::class, 'Unknown item type "command".');
});
