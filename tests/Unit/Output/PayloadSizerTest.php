<?php

declare(strict_types=1);

use Sift\Console\OutputPreferences;
use Sift\Console\OutputSize;
use Sift\Output\PayloadSizer;

function payloadSizerPreferences(OutputSize $size): OutputPreferences
{
    return new OutputPreferences(
        size: $size,
        pretty: false,
        showProcess: false,
        debug: false,
    );
}

/**
 * @return array<string, mixed>
 */
function payloadSizerPayload(): array
{
    return [
        'run_id' => '0td7j1a01z141z',
        'tool' => 'pest',
        'status' => 'failed',
        'summary' => [
            'tests' => 12,
            'failures' => 1,
        ],
        'items' => [
            [
                'type' => 'test_failure',
                'message' => 'Expected true to be false.',
                'file' => 'tests/Feature/CheckoutTest.php',
                'line' => 42,
                'test' => 'checkout blocks invalid cards',
                'diff' => '-true +false',
                'stdout' => 'verbose stdout',
                'stderr' => 'verbose stderr',
                'trace' => ['frame'],
                'raw' => ['native' => true],
            ],
        ],
        'artifacts' => [
            ['type' => 'junit', 'path' => 'build/junit.xml'],
        ],
        'extra' => [
            'native_exit_code' => 1,
        ],
        'meta' => [
            'subcommand' => 'pest',
            'warnings' => [],
            'duration' => 0.12,
        ],
    ];
}

it('keeps full payloads unchanged', function (): void {
    $payload = payloadSizerPayload();

    expect((new PayloadSizer())->resize($payload, payloadSizerPreferences(OutputSize::Full)))->toBe($payload);
});

it('renders compact payloads as tool, status, and flattened summary', function (): void {
    expect((new PayloadSizer())->resize(payloadSizerPayload(), payloadSizerPreferences(OutputSize::Compact)))->toBe([
        'run_id' => '0td7j1a01z141z',
        'tool' => 'pest',
        'status' => 'failed',
        'tests' => 12,
        'failures' => 1,
    ]);
});

it('renders normal payloads without verbose item fields or extra artifacts', function (): void {
    expect((new PayloadSizer())->resize(payloadSizerPayload(), payloadSizerPreferences(OutputSize::Normal)))->toBe([
        'run_id' => '0td7j1a01z141z',
        'tool' => 'pest',
        'status' => 'failed',
        'summary' => [
            'tests' => 12,
            'failures' => 1,
        ],
        'items' => [
            [
                'type' => 'test_failure',
                'message' => 'Expected true to be false.',
                'file' => 'tests/Feature/CheckoutTest.php',
                'line' => 42,
            ],
        ],
        'meta' => [
            'subcommand' => 'pest',
            'warnings' => [],
            'duration' => 0.12,
        ],
    ]);
});

it('does not resize error payloads', function (): void {
    $payload = [
        'status' => 'error',
        'error' => [
            'code' => 'invalid_usage',
            'message' => 'Unknown option.',
            'hint' => 'Run help.',
        ],
    ];

    expect((new PayloadSizer())->resize($payload, payloadSizerPreferences(OutputSize::Compact)))->toBe($payload);
});
