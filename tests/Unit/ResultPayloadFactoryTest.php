<?php

declare(strict_types=1);

use Sift\Core\NormalizedResult;
use Sift\Runtime\ResultPayloadFactory;

it('builds compact payloads from summary fields', function (): void {
    $payload = (new ResultPayloadFactory)->forSize(
        new NormalizedResult(
            tool: 'pint',
            status: 'failed',
            summary: ['files' => 2, 'fixers' => 7],
        ),
        'compact',
        'run1234',
    );

    expect($payload)->toBe([
        'status' => 'failed',
        'files' => 2,
        'fixers' => 7,
        'run_id' => 'run1234',
    ]);
});

it('keeps coverage threshold fields visible in compact payloads', function (): void {
    $payload = (new ResultPayloadFactory)->forSize(
        new NormalizedResult(
            tool: 'pest',
            status: 'failed',
            summary: [
                'tests' => 10,
                'coverage_percent' => 79.5,
                'coverage_min' => 80.0,
                'coverage_files_below_min' => 2,
            ],
        ),
        'compact',
    );

    expect($payload)->toBe([
        'status' => 'failed',
        'tests' => 10,
        'coverage_percent' => 79.5,
        'coverage_min' => 80.0,
        'coverage_files_below_min' => 2,
    ]);
});

it('builds normal payloads with summary and items', function (): void {
    $payload = (new ResultPayloadFactory)->forSize(
        new NormalizedResult(
            tool: 'phpstan',
            status: 'failed',
            summary: ['errors' => 1],
            items: [['file' => 'src/Broken.php', 'message' => 'Undefined property']],
        ),
        'normal',
    );

    expect($payload)->toBe([
        'status' => 'failed',
        'summary' => ['errors' => 1],
        'items' => [['file' => 'src/Broken.php', 'message' => 'Undefined property']],
    ]);
});

it('omits testcase names from normal payload items while keeping fuller payloads intact', function (): void {
    $result = new NormalizedResult(
        tool: 'pest',
        status: 'failed',
        summary: ['tests' => 1, 'failures' => 1],
        items: [[
            'type' => 'failure',
            'test' => 'it fails',
            'file' => 'tests/FailingTest.php',
            'line' => 6,
            'message' => 'Failed asserting that false is true.',
        ]],
    );

    $normal = (new ResultPayloadFactory)->forSize($result, 'normal');
    $fuller = (new ResultPayloadFactory)->forSize($result, 'fuller');

    expect($normal['items'][0])->not->toHaveKey('test')
        ->and($normal['items'][0])->toMatchArray([
            'type' => 'failure',
            'file' => 'tests/FailingTest.php',
            'line' => 6,
            'message' => 'Failed asserting that false is true.',
        ])
        ->and($fuller['items'][0]['test'])->toBe('it fails');
});

it('builds fuller payloads with the full normalized shape', function (): void {
    $payload = (new ResultPayloadFactory)->forSize(
        new NormalizedResult(
            tool: 'composer-audit',
            status: 'passed',
            summary: ['vulnerabilities' => 0],
            meta: ['exit_code' => 0],
        ),
        'fuller',
    );

    expect($payload)->toBe([
        'tool' => 'composer-audit',
        'status' => 'passed',
        'summary' => ['vulnerabilities' => 0],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => ['exit_code' => 0],
    ]);
});

it('compacts command payloads only when requested', function (): void {
    $factory = new ResultPayloadFactory;

    expect($factory->commandPayload([
        'status' => 'ok',
        'tool' => 'sift',
        'tools' => [['tool' => 'pint']],
        'path' => '/tmp/sift.json',
    ], 'compact'))->toBe([
        'status' => 'ok',
        'tool' => 'sift',
        'tools' => [['tool' => 'pint']],
    ]);
});
