<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Runtime\ToolLocator;
use Sift\Tools\ComposerAuditToolAdapter;
use Sift\Tools\PhpcsToolAdapter;
use Sift\Tools\PhpstanToolAdapter;
use Sift\Tools\PsalmToolAdapter;
use Sift\Tools\RectorToolAdapter;

it('normalizes composer audit advisories into vulnerability items', function (): void {
    $adapter = new ComposerAuditToolAdapter(new ToolLocator);
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 1,
            stdout: json_encode([
                'advisories' => [
                    'vendor/package' => [
                        [
                            'severity' => 'high',
                            'advisoryId' => 'PKSA-123',
                            'title' => 'Example advisory',
                            'cve' => 'CVE-2026-0001',
                            'link' => 'https://example.test/advisories/1',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 18,
        ),
        new PreparedCommand(['composer', 'audit', '--format=json'], siftRoot()),
        [],
    );

    expect($result->status)->toBe('failed')
        ->and($result->summary)->toBe([
            'vulnerabilities' => 1,
            'packages' => 1,
        ])
        ->and($result->items[0])->toMatchArray([
            'package' => 'vendor/package',
            'severity' => 'high',
            'advisory_id' => 'PKSA-123',
            'title' => 'Example advisory',
            'cve' => 'CVE-2026-0001',
            'link' => 'https://example.test/advisories/1',
        ])
        ->and($result->meta['exit_code'])->toBe(1);
});

it('normalizes phpstan file errors into items', function (): void {
    $adapter = new PhpstanToolAdapter(new ToolLocator);
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 1,
            stdout: json_encode([
                'totals' => [
                    'errors' => 2,
                    'file_errors' => 1,
                ],
                'files' => [
                    'src\\Example.php' => [
                        'messages' => [
                            'Call to an undefined method Example::missing().',
                            'Binary operation "." between int and string results in an error.',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 24,
        ),
        new PreparedCommand(['phpstan', 'analyse', '--error-format=json'], siftRoot()),
        [],
    );

    expect($result->status)->toBe('failed')
        ->and($result->summary)->toBe([
            'errors' => 2,
            'files' => 1,
        ])
        ->and($result->items)->toHaveCount(2)
        ->and($result->items[0])->toMatchArray([
            'file' => 'src/Example.php',
            'message' => 'Call to an undefined method Example::missing().',
        ]);
});

it('normalizes phpcs messages with line and fixer metadata', function (): void {
    $adapter = new PhpcsToolAdapter(new ToolLocator);
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 1,
            stdout: json_encode([
                'totals' => [
                    'errors' => 1,
                    'warnings' => 1,
                    'fixable' => 1,
                ],
                'files' => [
                    'src\\Style.php' => [
                        'messages' => [
                            [
                                'type' => 'ERROR',
                                'line' => 12,
                                'column' => 8,
                                'message' => 'Expected 1 space after IF keyword.',
                                'source' => 'Squiz.ControlStructures.ControlSignature.SpaceAfterKeyword',
                                'fixable' => true,
                            ],
                            [
                                'type' => 'WARNING',
                                'line' => 18,
                                'column' => 1,
                                'message' => 'Line exceeds 120 characters; contains 132 characters',
                                'source' => 'Generic.Files.LineLength.TooLong',
                                'fixable' => false,
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 16,
        ),
        new PreparedCommand(['phpcs', '--report=json'], siftRoot()),
        [],
    );

    expect($result->status)->toBe('failed')
        ->and($result->summary)->toBe([
            'errors' => 1,
            'warnings' => 1,
            'fixable' => 1,
            'files' => 1,
        ])
        ->and($result->items)->toHaveCount(2)
        ->and($result->items[0])->toMatchArray([
            'type' => 'error',
            'file' => 'src/Style.php',
            'line' => 12,
            'column' => 8,
            'rule' => 'Squiz.ControlStructures.ControlSignature.SpaceAfterKeyword',
            'fixable' => true,
        ]);
});

it('normalizes psalm issues with file and coordinate metadata', function (): void {
    $adapter = new PsalmToolAdapter(new ToolLocator);
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 1,
            stdout: json_encode([
                [
                    'severity' => 'error',
                    'type' => 'UndefinedMethod',
                    'message' => 'Method Demo::missing does not exist',
                    'file_path' => 'src\\Demo.php',
                    'line_from' => 27,
                    'column_from' => 15,
                ],
                [
                    'severity' => 'info',
                    'type' => 'UnusedVariable',
                    'message' => 'Unused variable $value',
                    'file_name' => 'src\\Other.php',
                    'line_from' => 9,
                    'column_from' => 5,
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 21,
        ),
        new PreparedCommand(['psalm', '--output-format=json'], siftRoot()),
        [],
    );

    expect($result->status)->toBe('failed')
        ->and($result->summary)->toBe([
            'issues' => 2,
            'files' => 2,
        ])
        ->and($result->items)->toHaveCount(2)
        ->and($result->items[0])->toMatchArray([
            'type' => 'error',
            'rule' => 'UndefinedMethod',
            'file' => 'src/Demo.php',
            'line' => 27,
            'column' => 15,
        ]);
});

it('normalizes rector dry-run changes and errors from noisy json output', function (): void {
    $adapter = new RectorToolAdapter(new ToolLocator);
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 1,
            stdout: "Rector 2.x\n".json_encode([
                'totals' => [
                    'changed_files' => 1,
                    'errors' => 1,
                ],
                'file_diffs' => [
                    [
                        'file' => 'src\\Legacy.php',
                        'diff' => "@@ -1 +1 @@\n-old\n+new\n",
                        'applied_rectors' => [
                            'Rector\\CodeQuality\\Rector\\If_\\SimplifyIfReturnBoolRector',
                        ],
                    ],
                ],
                'errors' => [
                    [
                        'file' => 'src\\Broken.php',
                        'line' => 44,
                        'message' => 'Could not parse the file.',
                        'caused_by' => 'Syntax error',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 30,
        ),
        new PreparedCommand(
            ['rector', 'process', '--dry-run', '--output-format=json'],
            siftRoot(),
            metadata: ['dry_run' => true],
        ),
        ['dry_run' => true],
    );

    expect($result->status)->toBe('error')
        ->and($result->summary)->toBe([
            'changed_files' => 1,
            'errors' => 1,
        ])
        ->and($result->artifacts)->toHaveCount(1)
        ->and($result->artifacts[0])->toMatchArray([
            'file' => 'src/Legacy.php',
            'applied_rectors' => [
                'Rector\\CodeQuality\\Rector\\If_\\SimplifyIfReturnBoolRector',
            ],
        ])
        ->and($result->items)->toHaveCount(2)
        ->and($result->items[0]['type'])->toBe('change')
        ->and($result->items[1])->toMatchArray([
            'type' => 'error',
            'file' => 'src/Broken.php',
            'line' => 44,
            'caused_by' => 'Syntax error',
        ]);
});
