<?php

declare(strict_types=1);

it('normalizes passing psalm executions', function (): void {
    $cwd = makeTempDirectory();

    try {
        createFakePsalmBinary($cwd, []);

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'psalm',
            'src',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('psalm')
            ->and($payload['status'])->toBe('passed')
            ->and($payload['summary'])->toBe([
                'issues' => 0,
                'files' => 0,
            ])
            ->and($payload['items'])->toBe([])
            ->and($payload['meta']['command'])->toContain('--output-format=json')
            ->and($payload['meta']['command'])->toContain('--no-progress')
            ->and($payload['meta']['exit_code'])->toBe(0)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes failing psalm executions with issue details', function (): void {
    $cwd = makeTempDirectory();

    try {
        createFakePsalmBinary($cwd, [
            [
                'severity' => 'error',
                'type' => 'UndefinedClass',
                'message' => 'Class App\\MissingClass does not exist',
                'file_path' => str_replace('\\', '/', $cwd).'/src/Broken.php',
                'line_from' => 12,
                'column_from' => 7,
            ],
            [
                'severity' => 'info',
                'type' => 'UnusedVariable',
                'message' => 'Possibly unused variable $value',
                'file_name' => str_replace('\\', '/', $cwd).'/src/Broken.php',
                'line_from' => 18,
                'column_from' => 3,
            ],
        ], 2);

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'psalm',
            'src',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('psalm')
            ->and($payload['status'])->toBe('failed')
            ->and($payload['summary'])->toBe([
                'issues' => 2,
                'files' => 1,
            ])
            ->and($payload['items'][0])->toBe([
                'type' => 'error',
                'message' => 'Class App\\MissingClass does not exist',
                'rule' => 'UndefinedClass',
                'file' => str_replace('\\', '/', $cwd).'/src/Broken.php',
                'line' => 12,
                'column' => 7,
            ])
            ->and($payload['items'][1])->toBe([
                'type' => 'info',
                'message' => 'Possibly unused variable $value',
                'rule' => 'UnusedVariable',
                'file' => str_replace('\\', '/', $cwd).'/src/Broken.php',
                'line' => 18,
                'column' => 3,
            ])
            ->and($payload['meta']['exit_code'])->toBe(2)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

/**
 * @param  list<array<string, mixed>>  $payload
 */
function createFakePsalmBinary(string $cwd, array $payload, int $exitCode = 0): void
{
    $json = var_export(json_encode($payload, JSON_THROW_ON_ERROR), true);

    createProjectTool($cwd, 'psalm', <<<PHP
<?php

declare(strict_types=1);

\$arguments = array_slice(\$_SERVER['argv'] ?? [], 1);
\$required = ['--output-format=json', '--no-progress'];

foreach (\$required as \$flag) {
    if (! in_array(\$flag, \$arguments, true)) {
        fwrite(STDERR, "missing flag: {\$flag}\\n");
        exit(9);
    }
}

echo $json;

exit($exitCode);
PHP);
}
