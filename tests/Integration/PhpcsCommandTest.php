<?php

declare(strict_types=1);

it('normalizes passing phpcs executions', function (): void {
    $cwd = makeTempDirectory();

    try {
        $payload = [
            'totals' => [
                'errors' => 0,
                'warnings' => 0,
                'fixable' => 0,
            ],
            'files' => new stdClass,
        ];

        createFakePhpcsBinary($cwd, $payload, 0);

        $process = runSift(['--no-history', '--size=fuller', '--format=json', '--pretty', 'phpcs', 'src'], $cwd);

        expect($process->getExitCode())->toBe(0);

        $decoded = decodeJsonOutput($process);

        expect($decoded['status'])->toBe('passed')
            ->and($decoded['summary'])->toBe([
                'errors' => 0,
                'warnings' => 0,
                'fixable' => 0,
                'files' => 0,
            ])
            ->and($decoded['items'])->toBe([])
            ->and($decoded['meta']['command'])->toContain('--report=json')
            ->and($decoded['meta']['command'])->toContain('--no-colors')
            ->and($decoded['meta']['command'])->toContain('-q');
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes failing phpcs executions with issue details', function (): void {
    $cwd = makeTempDirectory();

    try {
        $file = str_replace('\\', '/', $cwd).'/src/Broken.php';
        $payload = [
            'totals' => [
                'errors' => 1,
                'warnings' => 1,
                'fixable' => 1,
            ],
            'files' => [
                $file => [
                    'errors' => 1,
                    'warnings' => 1,
                    'messages' => [
                        [
                            'message' => 'Missing file doc comment',
                            'source' => 'Squiz.Commenting.FileComment.Missing',
                            'severity' => 5,
                            'fixable' => false,
                            'type' => 'ERROR',
                            'line' => 1,
                            'column' => 1,
                        ],
                        [
                            'message' => 'Opening brace should be on a new line',
                            'source' => 'PSR2.Classes.ClassDeclaration.OpenBraceNewLine',
                            'severity' => 5,
                            'fixable' => true,
                            'type' => 'WARNING',
                            'line' => 3,
                            'column' => 12,
                        ],
                    ],
                ],
            ],
        ];

        createFakePhpcsBinary($cwd, $payload, 1);

        $process = runSift(['--no-history', '--size=fuller', '--format=json', '--pretty', 'phpcs', 'src'], $cwd);

        expect($process->getExitCode())->toBe(0);

        $decoded = decodeJsonOutput($process);

        expect($decoded['status'])->toBe('failed')
            ->and($decoded['summary'])->toBe([
                'errors' => 1,
                'warnings' => 1,
                'fixable' => 1,
                'files' => 1,
            ])
            ->and($decoded['items'][0])->toBe([
                'type' => 'error',
                'file' => $file,
                'line' => 1,
                'column' => 1,
                'message' => 'Missing file doc comment',
                'rule' => 'Squiz.Commenting.FileComment.Missing',
                'fixable' => false,
            ])
            ->and($decoded['items'][1])->toBe([
                'type' => 'warning',
                'file' => $file,
                'line' => 3,
                'column' => 12,
                'message' => 'Opening brace should be on a new line',
                'rule' => 'PSR2.Classes.ClassDeclaration.OpenBraceNewLine',
                'fixable' => true,
            ]);
    } finally {
        removeDirectory($cwd);
    }
});

/**
 * @param  array<string, mixed>  $payload
 */
function createFakePhpcsBinary(string $cwd, array $payload, int $exitCode): void
{
    $json = var_export(json_encode($payload, JSON_THROW_ON_ERROR), true);

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

echo {$json};

exit({$exitCode});
PHP);
}
