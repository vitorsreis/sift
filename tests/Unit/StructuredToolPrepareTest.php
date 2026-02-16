<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Runtime\ToolLocator;
use Sift\Tools\PhpcsToolAdapter;
use Sift\Tools\PhpstanToolAdapter;
use Sift\Tools\PintToolAdapter;
use Sift\Tools\PsalmToolAdapter;

it('prepares pint in test mode by default and keeps repair mode explicit', function (): void {
    $cwd = makeTempDirectory('sift-pint-prepare-');

    try {
        createProjectTool($cwd, 'pint', "<?php\n");
        $adapter = new PintToolAdapter(new ToolLocator(PHP_BINARY));

        $default = $adapter->prepare($cwd, [], ['tool_binary' => 'vendor/bin/pint']);
        $repair = $adapter->prepare($cwd, ['--repair'], ['tool_binary' => 'vendor/bin/pint']);
        $fallback = $adapter->parse(
            new ExecutionResult(2, json_encode(['files' => []], JSON_THROW_ON_ERROR), '', 8),
            new PreparedCommand(['pint', '--format=json'], $cwd, metadata: ['mode' => 'test']),
            ['mode' => 'test'],
        );

        expect($adapter->detectContext([]))->toMatchArray([
            'arguments' => [],
            'mode' => 'fix',
        ])
            ->and($default->command)->toContain('--format=json', '--test')
            ->and($default->metadata['mode'])->toBe('test')
            ->and($repair->command)->toContain('--repair', '--format=json')
            ->and($repair->command)->not->toContain('--test')
            ->and($repair->metadata['mode'])->toBe('repair')
            ->and($fallback->status)->toBe('error');
    } finally {
        removeDirectory($cwd);
    }
});

it('prepares phpstan with analyse json and no-progress defaults', function (): void {
    $cwd = makeTempDirectory('sift-phpstan-prepare-');

    try {
        createProjectTool($cwd, 'phpstan', "<?php\n");
        $adapter = new PhpstanToolAdapter(new ToolLocator(PHP_BINARY));

        $default = $adapter->prepare($cwd, [], ['tool_binary' => 'vendor/bin/phpstan']);
        $custom = $adapter->prepare($cwd, ['analyse', 'src', '--error-format=json', '--no-progress'], ['tool_binary' => 'vendor/bin/phpstan']);

        expect($adapter->detectContext([]))->toMatchArray([
            'arguments' => [],
            'has_paths' => false,
        ])
            ->and($default->command)->toContain('analyse', '--error-format=json', '--no-progress')
            ->and(array_count_values($custom->command)['--error-format=json'] ?? 0)->toBe(1)
            ->and(array_count_values($custom->command)['--no-progress'] ?? 0)->toBe(1);
    } finally {
        removeDirectory($cwd);
    }
});

it('prepares phpcs with quiet json defaults without duplicating flags', function (): void {
    $cwd = makeTempDirectory('sift-phpcs-prepare-');

    try {
        createProjectTool($cwd, 'phpcs', "<?php\n");
        $adapter = new PhpcsToolAdapter(new ToolLocator(PHP_BINARY));

        $default = $adapter->prepare($cwd, ['src'], ['tool_binary' => 'vendor/bin/phpcs']);
        $custom = $adapter->prepare($cwd, ['src', '--report=json', '-q', '--no-colors'], ['tool_binary' => 'vendor/bin/phpcs']);

        expect($adapter->detectContext(['src']))->toMatchArray([
            'arguments' => ['src'],
            'has_paths' => true,
        ])
            ->and($default->command)->toContain('--report=json', '-q', '--no-colors')
            ->and(array_count_values($custom->command)['--report=json'] ?? 0)->toBe(1)
            ->and(array_count_values($custom->command)['-q'] ?? 0)->toBe(1)
            ->and(array_count_values($custom->command)['--no-colors'] ?? 0)->toBe(1);
    } finally {
        removeDirectory($cwd);
    }
});

it('prepares psalm with json and no-progress defaults', function (): void {
    $cwd = makeTempDirectory('sift-psalm-prepare-');

    try {
        createProjectTool($cwd, 'psalm', "<?php\n");
        $adapter = new PsalmToolAdapter(new ToolLocator(PHP_BINARY));

        $default = $adapter->prepare($cwd, ['src'], ['tool_binary' => 'vendor/bin/psalm']);
        $custom = $adapter->prepare($cwd, ['src', '--output-format=json', '--no-progress'], ['tool_binary' => 'vendor/bin/psalm']);
        $passed = $adapter->parse(
            new ExecutionResult(0, json_encode([], JSON_THROW_ON_ERROR), '', 7),
            new PreparedCommand(['psalm', '--output-format=json'], $cwd),
            [],
        );

        expect($adapter->detectContext(['src']))->toMatchArray([
            'arguments' => ['src'],
            'has_paths' => true,
        ])
            ->and($default->command)->toContain('--output-format=json', '--no-progress')
            ->and(array_count_values($custom->command)['--output-format=json'] ?? 0)->toBe(1)
            ->and(array_count_values($custom->command)['--no-progress'] ?? 0)->toBe(1)
            ->and($passed->status)->toBe('passed');
    } finally {
        removeDirectory($cwd);
    }
});
