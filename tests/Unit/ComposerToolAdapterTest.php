<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use Sift\Tools\ComposerToolAdapter;

it('detects composer subcommands and outdated mode consistently', function (): void {
    $adapter = new ComposerToolAdapter(new ToolLocator(PHP_BINARY));

    expect($adapter->detectContext(['licenses']))->toMatchArray([
        'subcommand' => 'licenses',
        'mode' => 'licenses',
    ])
        ->and($adapter->detectContext(['show', '--outdated']))->toMatchArray([
            'subcommand' => 'show',
            'mode' => 'outdated',
        ])
        ->and($adapter->detectContext(['--profile']))->toMatchArray([
            'subcommand' => '',
            'mode' => 'show',
        ]);
});

it('injects json formatting while preparing composer commands', function (): void {
    $cwd = makeTempDirectory('sift-composer-prepare-');

    try {
        createProjectTool($cwd, 'composer', "<?php\n");
        $adapter = new ComposerToolAdapter(new ToolLocator(PHP_BINARY));

        $prepared = $adapter->prepare($cwd, ['licenses'], [
            'tool_binary' => 'vendor/bin/composer',
            'subcommand' => 'licenses',
            'mode' => 'licenses',
        ]);

        expect($prepared->command[0] ?? null)->toBe(PHP_BINARY)
            ->and(str_replace('\\', '/', (string) ($prepared->command[1] ?? '')))->toContain('vendor/bin/composer')
            ->and($prepared->command)->toContain('licenses')
            ->and($prepared->command)->toContain('--format=json')
            ->and($prepared->metadata)->toMatchArray([
                'subcommand' => 'licenses',
                'mode' => 'licenses',
            ]);
    } finally {
        removeDirectory($cwd);
    }
});

it('does not duplicate explicit composer format flags during prepare', function (): void {
    $cwd = makeTempDirectory('sift-composer-format-');

    try {
        createProjectTool($cwd, 'composer', "<?php\n");
        $adapter = new ComposerToolAdapter(new ToolLocator(PHP_BINARY));

        $prepared = $adapter->prepare($cwd, ['show', '-f', 'json'], [
            'tool_binary' => 'vendor/bin/composer',
        ]);
        $formatFlags = array_values(array_filter(
            $prepared->command,
            static fn (string $argument): bool => $argument === '--format=json' || $argument === '-f',
        ));

        expect($formatFlags)->toBe(['-f']);
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes composer show package lists and abandoned replacements', function (): void {
    $adapter = new ComposerToolAdapter(new ToolLocator(PHP_BINARY));
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 0,
            stdout: json_encode([
                [
                    'name' => 'vendor/one',
                    'version' => '1.0.0',
                    'latest' => '1.0.0',
                    'latest-status' => 'up-to-date',
                    'description' => 'Stable package',
                    'abandoned' => 'vendor/replacement',
                ],
                [
                    'name' => 'vendor/two',
                    'version' => '1.0.0',
                    'latest' => '2.0.0',
                    'latest-status' => 'semver-safe-update',
                    'description' => 'Needs an update',
                    'abandoned' => false,
                ],
                [
                    'version' => 'missing-name',
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 15,
        ),
        new PreparedCommand(['composer', 'show', '--format=json'], siftRoot(), metadata: [
            'subcommand' => 'show',
            'mode' => 'show',
        ]),
        [
            'subcommand' => 'show',
            'mode' => 'show',
        ],
    );

    expect($result->status)->toBe('passed')
        ->and($result->summary)->toBe([
            'packages' => 3,
            'outdated' => 1,
            'abandoned' => 1,
        ])
        ->and($result->items)->toBe([
            [
                'package' => 'vendor/one',
                'version' => '1.0.0',
                'latest' => '1.0.0',
                'latest_status' => 'up-to-date',
                'description' => 'Stable package',
                'abandoned' => true,
                'replacement' => 'vendor/replacement',
            ],
            [
                'package' => 'vendor/two',
                'version' => '1.0.0',
                'latest' => '2.0.0',
                'latest_status' => 'semver-safe-update',
                'description' => 'Needs an update',
            ],
        ])
        ->and($result->meta)->toMatchArray([
            'subcommand' => 'show',
            'mode' => 'show',
        ]);
});

it('filters composer show output down to outdated packages in outdated mode', function (): void {
    $adapter = new ComposerToolAdapter(new ToolLocator(PHP_BINARY));
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 0,
            stdout: json_encode([
                'installed' => [
                    [
                        'name' => 'vendor/up-to-date',
                        'version' => '1.0.0',
                        'latest' => '1.0.0',
                        'latest-status' => 'up-to-date',
                    ],
                    [
                        'name' => 'vendor/outdated',
                        'version' => '1.0.0',
                        'latest' => '1.1.0',
                        'latest-status' => 'semver-safe-update',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 19,
        ),
        new PreparedCommand(['composer', 'show', '--outdated', '--format=json'], siftRoot(), metadata: [
            'subcommand' => 'show',
            'mode' => 'outdated',
        ]),
        [
            'subcommand' => 'show',
            'mode' => 'outdated',
        ],
    );

    expect($result->status)->toBe('failed')
        ->and($result->summary)->toBe([
            'packages' => 2,
            'outdated' => 1,
            'abandoned' => 0,
        ])
        ->and($result->items)->toBe([
            [
                'package' => 'vendor/outdated',
                'version' => '1.0.0',
                'latest' => '1.1.0',
                'latest_status' => 'semver-safe-update',
            ],
        ]);
});

it('normalizes composer licenses from keyed and list dependencies', function (): void {
    $adapter = new ComposerToolAdapter(new ToolLocator(PHP_BINARY));
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 0,
            stdout: json_encode([
                'name' => 'vitorsreis/sift',
                'version' => '1.0.0',
                'license' => 'MIT',
                'dependencies' => [
                    'symfony/process' => ['MIT', 'Apache-2.0'],
                    [
                        'name' => 'pestphp/pest',
                        'licenses' => ['MIT'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 17,
        ),
        new PreparedCommand(['composer', 'licenses', '--format=json'], siftRoot(), metadata: [
            'subcommand' => 'licenses',
            'mode' => 'licenses',
        ]),
        [
            'subcommand' => 'licenses',
            'mode' => 'licenses',
        ],
    );

    expect($result->status)->toBe('passed')
        ->and($result->summary)->toBe([
            'dependencies' => 2,
            'licenses' => 2,
        ])
        ->and($result->items)->toBe([
            [
                'package' => 'symfony/process',
                'licenses' => ['MIT', 'Apache-2.0'],
            ],
            [
                'package' => 'pestphp/pest',
                'licenses' => ['MIT'],
            ],
        ])
        ->and($result->extra['root_package'])->toBe([
            'name' => 'vitorsreis/sift',
            'version' => '1.0.0',
            'licenses' => ['MIT'],
        ]);
});

dataset('composer_audit_statuses', [
    'passed with no advisories' => [0, 'passed'],
    'errors without advisories' => [1, 'error'],
]);

it('maps composer audit status when no advisories are present', function (int $exitCode, string $status): void {
    $adapter = new ComposerToolAdapter(new ToolLocator(PHP_BINARY));
    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: $exitCode,
            stdout: json_encode(['advisories' => []], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 14,
        ),
        new PreparedCommand(['composer', 'audit', '--format=json'], siftRoot(), metadata: [
            'subcommand' => 'audit',
            'mode' => 'audit',
        ]),
        [
            'subcommand' => 'audit',
            'mode' => 'audit',
        ],
    );

    expect($result->status)->toBe($status)
        ->and($result->summary)->toBe([
            'vulnerabilities' => 0,
            'packages' => 0,
        ]);
})->with('composer_audit_statuses');

it('throws a parse failure when composer does not emit valid json', function (): void {
    $adapter = new ComposerToolAdapter(new ToolLocator(PHP_BINARY));

    try {
        $adapter->parse(
            new ExecutionResult(
                exitCode: 1,
                stdout: 'not-json',
                stderr: 'still-not-json',
                duration: 10,
            ),
            new PreparedCommand(['composer', 'show', '--format=json'], siftRoot(), metadata: [
                'subcommand' => 'show',
                'mode' => 'show',
            ]),
            [
                'subcommand' => 'show',
                'mode' => 'show',
            ],
        );

        test()->fail('Expected a parse failure to be thrown.');
    } catch (UserFacingException $exception) {
        $payload = $exception->payload();

        expect($payload['status'])->toBe('error')
            ->and($payload['error']['code'])->toBe('parse_failure')
            ->and($payload['error']['tool'])->toBe('composer')
            ->and($payload['error']['message'])->toBe('Unable to parse Composer JSON output.');
    }
});
