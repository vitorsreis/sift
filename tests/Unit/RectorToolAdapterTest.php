<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Runtime\ToolLocator;
use Sift\Tools\RectorToolAdapter;

it('detects rector command context and injects dry-run json defaults', function (): void {
    $cwd = makeTempDirectory('sift-rector-prepare-');

    try {
        createProjectTool($cwd, 'rector', "<?php\n");
        $adapter = new RectorToolAdapter(new ToolLocator(PHP_BINARY));

        $defaultContext = $adapter->detectContext([]);
        $prepared = $adapter->prepare($cwd, ['--dry-run', 'src'], [
            ...$defaultContext,
            'tool_binary' => 'vendor/bin/rector',
        ]);
        $default = $adapter->prepare($cwd, [], ['tool_binary' => 'vendor/bin/rector']);

        expect($defaultContext)->toMatchArray([
            'arguments' => [],
            'command' => 'process',
            'dry_run' => false,
        ])
            ->and($prepared->command)->toContain('--dry-run', '--output-format=json')
            ->and($prepared->metadata)->toMatchArray([
                'command' => 'process',
                'dry_run' => true,
            ])
            ->and($default->command)->toContain('process', '--output-format=json');
    } finally {
        removeDirectory($cwd);
    }
});

it('does not duplicate explicit rector output formatting', function (): void {
    $cwd = makeTempDirectory('sift-rector-format-');

    try {
        createProjectTool($cwd, 'rector', "<?php\n");
        $adapter = new RectorToolAdapter(new ToolLocator(PHP_BINARY));

        $prepared = $adapter->prepare($cwd, ['process', '--output-format=json', '--dry-run'], [
            'tool_binary' => 'vendor/bin/rector',
        ]);

        expect(array_count_values($prepared->command)['--output-format=json'] ?? 0)->toBe(1);
    } finally {
        removeDirectory($cwd);
    }
});

it('marks rector changes as changed outside dry-run and omits optional fields when absent', function (): void {
    $adapter = new RectorToolAdapter(new ToolLocator(PHP_BINARY));
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 0,
            stdout: json_encode([
                'totals' => [
                    'changed_files' => 1,
                    'errors' => 1,
                ],
                'file_diffs' => [
                    [
                        'file' => 'src\\Demo.php',
                        'diff' => "@@ -1 +1 @@\n-old\n+new\n",
                        'applied_rectors' => [],
                    ],
                ],
                'errors' => [
                    [
                        'message' => 'Could not parse the file.',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 14,
        ),
        new PreparedCommand(['rector', 'process', '--output-format=json'], siftRoot(), metadata: [
            'dry_run' => false,
        ]),
        ['dry_run' => false],
    );

    expect($result->status)->toBe('error')
        ->and($result->artifacts)->toBe([
            [
                'file' => 'src/Demo.php',
                'diff' => "@@ -1 +1 @@\n-old\n+new\n",
            ],
        ])
        ->and($result->items)->toBe([
            [
                'type' => 'change',
                'file' => 'src/Demo.php',
            ],
            [
                'type' => 'error',
                'message' => 'Could not parse the file.',
            ],
        ]);

    $changed = $adapter->parse(
        new ExecutionResult(
            exitCode: 0,
            stdout: json_encode([
                'totals' => [
                    'changed_files' => 1,
                    'errors' => 0,
                ],
                'file_diffs' => [
                    [
                        'file' => 'src\\Demo.php',
                        'diff' => "@@ -1 +1 @@\n-old\n+new\n",
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 9,
        ),
        new PreparedCommand(['rector', 'process', '--output-format=json'], siftRoot(), metadata: [
            'dry_run' => false,
        ]),
        ['dry_run' => false],
    );

    expect($changed->status)->toBe('changed')
        ->and($changed->summary)->toBe([
            'changed_files' => 1,
            'errors' => 0,
        ]);
});
