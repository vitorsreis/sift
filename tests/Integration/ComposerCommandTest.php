<?php

declare(strict_types=1);

it('normalizes composer licenses output', function (): void {
    $cwd = makeTempDirectory();

    try {
        createFakeComposerBinary($cwd, [
            'licenses' => [
                'payload' => [
                    'name' => 'vitorsreis/sift',
                    'version' => '1.0.0',
                    'license' => ['MIT'],
                    'dependencies' => [
                        'pestphp/pest' => ['MIT'],
                        'symfony/process' => ['Apache-2.0', 'MIT'],
                    ],
                ],
                'exit' => 0,
            ],
        ]);
        writeComposerToolConfig($cwd);

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'composer',
            'licenses',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('composer')
            ->and($payload['status'])->toBe('passed')
            ->and($payload['summary'])->toBe([
                'dependencies' => 2,
                'licenses' => 2,
            ])
            ->and($payload['items'])->toBe([
                [
                    'package' => 'pestphp/pest',
                    'licenses' => ['MIT'],
                ],
                [
                    'package' => 'symfony/process',
                    'licenses' => ['Apache-2.0', 'MIT'],
                ],
            ])
            ->and($payload['extra']['root_package'])->toBe([
                'name' => 'vitorsreis/sift',
                'version' => '1.0.0',
                'licenses' => ['MIT'],
            ])
            ->and($payload['meta']['subcommand'])->toBe('licenses')
            ->and($payload['meta']['command'])->toContain('licenses')
            ->and($payload['meta']['command'])->toContain('--format=json');
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes composer outdated output with package details', function (): void {
    $cwd = makeTempDirectory();

    try {
        createFakeComposerBinary($cwd, [
            'outdated' => [
                'payload' => [
                    'installed' => [
                        [
                            'name' => 'symfony/process',
                            'version' => '7.1.0',
                            'latest' => '7.2.0',
                            'latest-status' => 'semver-safe-update',
                            'description' => 'Executes commands in sub-processes.',
                            'abandoned' => false,
                        ],
                        [
                            'name' => 'pestphp/pest',
                            'version' => '3.7.0',
                            'latest' => '3.7.0',
                            'latest-status' => 'up-to-date',
                            'description' => 'The elegant PHP testing framework.',
                            'abandoned' => false,
                        ],
                    ],
                ],
                'exit' => 0,
            ],
        ]);
        writeComposerToolConfig($cwd);

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'composer',
            'outdated',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('composer')
            ->and($payload['status'])->toBe('failed')
            ->and($payload['summary'])->toBe([
                'packages' => 2,
                'outdated' => 1,
                'abandoned' => 0,
            ])
            ->and($payload['items'])->toBe([
                [
                    'package' => 'symfony/process',
                    'version' => '7.1.0',
                    'latest' => '7.2.0',
                    'latest_status' => 'semver-safe-update',
                    'description' => 'Executes commands in sub-processes.',
                ],
            ])
            ->and($payload['meta']['subcommand'])->toBe('outdated')
            ->and($payload['meta']['mode'])->toBe('outdated');
    } finally {
        removeDirectory($cwd);
    }
});

it('rejects unsupported composer subcommands before executing the tool, even in raw mode', function (): void {
    $cwd = makeTempDirectory();
    $sentinel = $cwd.DIRECTORY_SEPARATOR.'composer-executed.txt';

    try {
        $sentinelExport = var_export($sentinel, true);

        createProjectTool($cwd, 'composer', <<<PHP
<?php

declare(strict_types=1);

file_put_contents($sentinelExport, 'executed');

echo "should not run";
PHP);
        writeComposerToolConfig($cwd);

        $process = runSift([
            '--raw',
            'composer',
            'install',
        ], $cwd);

        expect($process->getExitCode())->toBe(1)
            ->and($sentinel)->not->toBeFile();

        $payload = decodeJsonOutput($process);

        expect($payload['error']['code'])->toBe('invalid_usage')
            ->and($payload['error']['message'])->toContain('read-only Composer subcommands')
            ->and($payload['error']['message'])->toContain('install');
    } finally {
        removeDirectory($cwd);
    }
});

/**
 * @param  array<string, array{payload: mixed, exit: int}>  $responses
 */
function createFakeComposerBinary(string $cwd, array $responses): void
{
    $payload = var_export(json_encode($responses, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), true);

    createProjectTool($cwd, 'composer', <<<PHP
<?php

declare(strict_types=1);

\$responses = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
\$arguments = array_slice(\$_SERVER['argv'] ?? [], 1);
\$subcommand = '';

foreach (\$arguments as \$argument) {
    if (\$argument !== '' && ! str_starts_with(\$argument, '-')) {
        \$subcommand = \$argument;
        break;
    }
}

if (\$subcommand === '' || ! isset(\$responses[\$subcommand])) {
    fwrite(STDERR, "unsupported command\\n");
    exit(91);
}

if (! in_array('--format=json', \$arguments, true)) {
    fwrite(STDERR, "missing json flag\\n");
    exit(92);
}

echo json_encode(\$responses[\$subcommand]['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

exit((int) (\$responses[\$subcommand]['exit'] ?? 0));
PHP);
}

function writeComposerToolConfig(string $cwd): void
{
    writeSiftConfig($cwd, [
        'history' => ['enabled' => true],
        'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => true],
        'tools' => [
            'composer' => [
                'enabled' => true,
                'toolBinary' => 'vendor/bin/composer',
            ],
        ],
    ]);
}
