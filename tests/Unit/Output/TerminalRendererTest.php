<?php

declare(strict_types=1);

use Sift\Console\OutputPreferences;
use Sift\Console\OutputSize;
use Sift\Output\TerminalRenderer;

function terminalRendererPreferences(OutputSize $size): OutputPreferences
{
    return new OutputPreferences(
        size: $size,
        pretty: false,
        showProcess: false,
        debug: false,
    );
}

it('renders compact terminal output as one short line', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'pest',
        'status' => 'failed',
        'summary' => [
            'tests' => 12,
            'failures' => 1,
        ],
        'items' => [
            ['type' => 'test_failure', 'message' => 'Expected true to be false.'],
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toBe('pest failed tests=12 failures=1' . PHP_EOL);
});

it('renders normal terminal output with summary and non-verbose items', function (): void {
    $output = (new TerminalRenderer())->render([
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
                'stdout' => 'verbose stdout',
            ],
        ],
    ], terminalRendererPreferences(OutputSize::Normal));

    expect($output)->toBe(str_replace("\n", PHP_EOL, <<<'TEXT'
pest failed
summary: tests=12 failures=1
items:
- test_failure tests/Feature/CheckoutTest.php:42 Expected true to be false.
TEXT) . PHP_EOL);
});

it('renders version terminal output as a short version line', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'version' => '2.0.0',
        ],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'version',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toBe('Sift 2.0.0' . PHP_EOL);
});

it('renders tools list terminal output as status lines', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['supported' => 2, 'installed' => 1, 'enabled' => 2],
        'items' => [
            [
                'tool' => 'pest',
                'enabled' => true,
                'installed' => true,
                'version' => 'Pest 4.0.1',
            ],
            [
                'tool' => 'phpunit',
                'enabled' => true,
                'installed' => false,
                'version' => null,
                'install_hint' => 'composer require --dev phpunit/phpunit',
            ],
        ],
        'meta' => ['subcommand' => 'tools list'],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain('Supported tools and local availability.');
    expect($output)->toContain('OK');
    expect($output)->toContain('Pest 4.0.1');
    expect($output)->toContain('NO');
    expect($output)->toContain('PHPUnit, use `composer require --dev phpunit/phpunit`');
});

it('renders errors with code message hint and context', function (): void {
    $output = (new TerminalRenderer())->render([
        'status' => 'error',
        'error' => [
            'code' => 'invalid_usage',
            'message' => 'Unknown option "--bad".',
            'hint' => 'Run "sift help" to list available commands.',
            'argument' => '--bad',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toBe(str_replace("\n", PHP_EOL, <<<'TEXT'
error invalid_usage
message: Unknown option "--bad".
hint: Run "sift help" to list available commands.
argument: --bad
TEXT) . PHP_EOL);
});
