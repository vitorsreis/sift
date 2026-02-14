<?php

declare(strict_types=1);

it('shows recent process lines when explicitly enabled', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProgressPhpcsBinary($cwd);

        $process = runSift(['--show-process', '--no-history', '--size=fuller', '--format=json', 'phpcs', 'src'], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);
        $stderr = str_replace("\r", '', $process->getErrorOutput());

        expect($payload['status'])->toBe('passed')
            ->and($stderr)->toContain('Scanning src')
            ->and($stderr)->toContain('Finished src')
            ->and($stderr)->toContain("\u{001b}[");
    } finally {
        removeDirectory($cwd);
    }
});

it('lets no-show-process override config defaults', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProgressPhpcsBinary($cwd);
        writeSiftConfig($cwd, [
            'history' => ['enabled' => true],
            'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => true, 'show_process' => true],
            'tools' => [
                'phpcs' => [
                    'enabled' => true,
                    'toolBinary' => 'vendor/bin/phpcs',
                ],
            ],
        ]);

        $process = runSift(['--no-show-process', '--no-history', 'phpcs', 'src'], $cwd);

        expect($process->getExitCode())->toBe(0)
            ->and(trim($process->getErrorOutput()))->toBe('');
    } finally {
        removeDirectory($cwd);
    }
});

function createProgressPhpcsBinary(string $cwd): void
{
    $payload = json_encode([
        'totals' => [
            'errors' => 0,
            'warnings' => 0,
            'fixable' => 0,
        ],
        'files' => new stdClass,
    ], JSON_THROW_ON_ERROR);
    $payloadExport = var_export($payload, true);

    createProjectTool($cwd, 'phpcs', <<<PHP
<?php

declare(strict_types=1);

\$arguments = array_slice(\$_SERVER['argv'] ?? [], 1);
\$required = ['--report=json', '-q', '--no-colors'];

foreach (\$required as \$flag) {
    if (! in_array(\$flag, \$arguments, true)) {
        fwrite(STDERR, "missing flag: {\$flag}\\n");
        exit(9);
    }
}

fwrite(STDERR, "Scanning src\\n");
usleep(5000);
fwrite(STDERR, "Finished src\\n");
echo $payloadExport;
PHP);
}
