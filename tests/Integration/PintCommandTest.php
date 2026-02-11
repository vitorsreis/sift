<?php

declare(strict_types=1);

it('normalizes passing pint executions', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProxyToolBinary($cwd, 'pint', siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pint');
        file_put_contents($cwd.DIRECTORY_SEPARATOR.'Good.php', "<?php\n\ndeclare(strict_types=1);\n");

        $process = runSift(['--no-history', '--size=fuller', '--format=json', '--pretty', 'pint', $cwd], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['status'])->toBe('passed')
            ->and($payload['meta']['mode'])->toBe('test')
            ->and($payload['summary'])->toBe(['files' => 0, 'fixers' => 0]);
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes failing pint executions with file and fixer details', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProxyToolBinary($cwd, 'pint', siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pint');
        file_put_contents($cwd.DIRECTORY_SEPARATOR.'Bad.php', "<?php\n\nclass Bad{public function hi( ) {return 1;}}");

        $process = runSift(['--no-history', '--size=fuller', '--format=json', '--pretty', 'pint', $cwd], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['status'])->toBe('failed')
            ->and($payload['summary']['files'])->toBeGreaterThanOrEqual(1)
            ->and($payload['summary']['fixers'])->toBeGreaterThanOrEqual(1)
            ->and($payload['items'][0]['file'])->toContain('Bad.php')
            ->and($payload['items'][0]['fixers'])->not->toBe([]);
    } finally {
        removeDirectory($cwd);
    }
});
