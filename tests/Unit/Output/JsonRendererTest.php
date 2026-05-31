<?php

declare(strict_types=1);

use Sift\Console\OutputPreferences;
use Sift\Console\OutputSize;
use Sift\Output\JsonRenderer;

it('renders compact json by default', function (): void {
    $json = (new JsonRenderer())->render(['status' => 'passed']);

    expect($json)->toBe('{"status":"passed"}' . PHP_EOL);
});

it('renders pretty json when requested', function (): void {
    $json = (new JsonRenderer())->render(
        payload: ['status' => 'passed', 'summary' => ['count' => 1]],
        preferences: new OutputPreferences(
            size: OutputSize::Full,
            pretty: true,
            showProcess: false,
            debug: false,
        ),
    );

    expect($json)->toBe(<<<'JSON'
{
    "status": "passed",
    "summary": {
        "count": 1
    }
}
JSON . PHP_EOL);
});

it('resizes payloads before rendering', function (): void {
    $json = (new JsonRenderer())->render(
        payload: [
            'tool' => 'pest',
            'status' => 'failed',
            'summary' => ['tests' => 12, 'failures' => 1],
            'items' => [['type' => 'test_failure', 'message' => 'Failed.']],
            'artifacts' => [],
            'extra' => [],
            'meta' => ['subcommand' => 'pest'],
        ],
        preferences: new OutputPreferences(
            size: OutputSize::Compact,
            pretty: false,
            showProcess: false,
            debug: false,
        ),
    );

    expect($json)->toBe('{"tool":"pest","status":"failed","tests":12,"failures":1}' . PHP_EOL);
});
