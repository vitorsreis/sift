<?php

declare(strict_types=1);

it('normalizes passing rector dry-run executions', function (): void {
    $cwd = makeTempDirectory();

    try {
        createFakeRectorBinary($cwd, [
            'totals' => [
                'changed_files' => 0,
                'errors' => 0,
            ],
        ]);

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'rector',
            '--dry-run',
            'src',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('rector')
            ->and($payload['status'])->toBe('passed')
            ->and($payload['summary'])->toBe([
                'changed_files' => 0,
                'errors' => 0,
            ])
            ->and($payload['items'])->toBe([])
            ->and($payload['artifacts'])->toBe([])
            ->and($payload['meta']['command'][0] ?? null)->toBe(PHP_BINARY)
            ->and(str_replace('\\', '/', (string) ($payload['meta']['command'][1] ?? '')))->toContain('vendor/bin/rector')
            ->and($payload['meta']['command'])->toContain('process')
            ->and($payload['meta']['command'])->toContain('--dry-run')
            ->and($payload['meta']['command'])->toContain('--output-format=json')
            ->and($payload['meta']['dry_run'])->toBeTrue()
            ->and($payload['meta']['exit_code'])->toBe(0)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes failing rector dry-run executions with diff details', function (): void {
    $cwd = makeTempDirectory();
    $file = str_replace('\\', '/', $cwd).'/src/Demo.php';

    try {
        createFakeRectorBinary($cwd, [
            'totals' => [
                'changed_files' => 1,
                'errors' => 0,
            ],
            'changed_files' => [$file],
            'file_diffs' => [[
                'file' => $file,
                'diff' => "--- Original\n+++ New\n@@ -1,4 +1,4 @@\n-class Demo {}\n+final class Demo {}\n",
                'applied_rectors' => [
                    'Rector\\CodeQuality\\Rector\\Class_\\FinalizeClassesWithoutChildrenRector',
                ],
            ]],
        ], 2);

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'rector',
            'process',
            '--dry-run',
            'src',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('rector')
            ->and($payload['status'])->toBe('failed')
            ->and($payload['summary'])->toBe([
                'changed_files' => 1,
                'errors' => 0,
            ])
            ->and($payload['items'])->toBe([
                [
                    'type' => 'change',
                    'file' => $file,
                    'message' => 'Rector suggested changes.',
                    'diff' => "--- Original\n+++ New\n@@ -1,4 +1,4 @@\n-class Demo {}\n+final class Demo {}\n",
                    'applied_rectors' => [
                        'Rector\\CodeQuality\\Rector\\Class_\\FinalizeClassesWithoutChildrenRector',
                    ],
                ],
            ])
            ->and($payload['artifacts'])->toBe([
                [
                    'file' => $file,
                    'diff' => "--- Original\n+++ New\n@@ -1,4 +1,4 @@\n-class Demo {}\n+final class Demo {}\n",
                    'applied_rectors' => [
                        'Rector\\CodeQuality\\Rector\\Class_\\FinalizeClassesWithoutChildrenRector',
                    ],
                ],
            ])
            ->and($payload['meta']['dry_run'])->toBeTrue()
            ->and($payload['meta']['exit_code'])->toBe(2)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

it('rejects rector write mode before executing the tool, even in raw mode', function (): void {
    $cwd = makeTempDirectory();
    $sentinel = $cwd.DIRECTORY_SEPARATOR.'rector-executed.txt';

    try {
        createProjectTool($cwd, 'rector', <<<'PHP'
<?php

declare(strict_types=1);

file_put_contents(__DIR__.'/../../rector-executed.txt', 'executed');

echo "should not run";
PHP);

        $process = runSift([
            '--raw',
            'rector',
            'process',
            'src',
        ], $cwd);

        expect($process->getExitCode())->toBe(1)
            ->and($sentinel)->not->toBeFile();

        $payload = decodeJsonOutput($process);

        expect($payload['error']['code'])->toBe('invalid_usage')
            ->and($payload['error']['message'])->toBe('Rector write mode is blocked by Sift. Run `rector process --dry-run ...` instead.');
    } finally {
        removeDirectory($cwd);
    }
});

/**
 * @param  array<string, mixed>  $payload
 */
function createFakeRectorBinary(string $cwd, array $payload, int $exitCode = 0): void
{
    $json = var_export(json_encode($payload, JSON_THROW_ON_ERROR), true);

    createProjectTool($cwd, 'rector', <<<PHP
<?php

declare(strict_types=1);

\$arguments = array_slice(\$_SERVER['argv'] ?? [], 1);

if ((\$arguments[0] ?? null) !== 'process') {
    fwrite(STDERR, "missing process command\\n");
    exit(9);
}

if (! in_array('--dry-run', \$arguments, true)) {
    fwrite(STDERR, "missing dry-run flag\\n");
    exit(8);
}

if (! in_array('--output-format=json', \$arguments, true)) {
    fwrite(STDERR, "missing json flag\\n");
    exit(7);
}

echo {$json};

exit({$exitCode});
PHP);
}
