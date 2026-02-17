<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Runtime\ToolLocator;
use Sift\Tools\ComposerAuditToolAdapter;

it('detects composer audit context and injects json formatting during prepare', function (): void {
    $cwd = makeTempDirectory('sift-composer-audit-');

    try {
        createProjectTool($cwd, 'composer', "<?php\n");
        $adapter = new ComposerAuditToolAdapter(new ToolLocator(PHP_BINARY));

        $context = $adapter->detectContext(['--locked']);
        $prepared = $adapter->prepare($cwd, ['--locked'], [
            ...$context,
            'tool_binary' => 'vendor/bin/composer',
        ]);

        expect($context)->toBe([
            'arguments' => ['--locked'],
        ])
            ->and($prepared->command[0] ?? null)->toBe(PHP_BINARY)
            ->and(str_replace('\\', '/', (string) ($prepared->command[1] ?? '')))->toContain('vendor/bin/composer')
            ->and($prepared->command)->toContain('audit')
            ->and($prepared->command)->toContain('--format=json')
            ->and($prepared->command)->toContain('--locked');
    } finally {
        removeDirectory($cwd);
    }
});

it('does not duplicate explicit composer audit format flags', function (): void {
    $cwd = makeTempDirectory('sift-composer-audit-format-');

    try {
        createProjectTool($cwd, 'composer', "<?php\n");
        $adapter = new ComposerAuditToolAdapter(new ToolLocator(PHP_BINARY));

        $prepared = $adapter->prepare($cwd, ['--format=json'], [
            'tool_binary' => 'vendor/bin/composer',
        ]);

        expect(array_count_values($prepared->command)['--format=json'] ?? 0)->toBe(1);
    } finally {
        removeDirectory($cwd);
    }
});

it('marks composer audit as passed without advisories and omits empty advisory fields', function (): void {
    $adapter = new ComposerAuditToolAdapter(new ToolLocator(PHP_BINARY));
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 0,
            stdout: json_encode([
                'advisories' => [
                    'vendor/package' => [
                        [
                            'severity' => 'critical',
                            'advisoryId' => 'PKSA-1',
                            'title' => '',
                            'cve' => '',
                            'link' => '',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 12,
        ),
        new PreparedCommand(['composer', 'audit', '--format=json'], siftRoot()),
        [],
    );

    expect($result->status)->toBe('failed')
        ->and($result->items)->toBe([
            [
                'package' => 'vendor/package',
                'severity' => 'critical',
                'advisory_id' => 'PKSA-1',
            ],
        ]);

    $passed = $adapter->parse(
        new ExecutionResult(
            exitCode: 0,
            stdout: json_encode(['advisories' => []], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 8,
        ),
        new PreparedCommand(['composer', 'audit', '--format=json'], siftRoot()),
        [],
    );

    expect($passed->status)->toBe('passed')
        ->and($passed->summary)->toBe([
            'vulnerabilities' => 0,
            'packages' => 0,
        ])
        ->and($passed->items)->toBe([]);
});
