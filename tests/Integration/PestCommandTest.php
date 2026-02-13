<?php

declare(strict_types=1);

it('normalizes passing pest executions', function (): void {
    $cwd = makeTempDirectory();

    try {
        createPestProject($cwd);
        createProxyToolBinary($cwd, 'pest', siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest');

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'pest',
            '--configuration',
            'phpunit.xml',
            'tests/PassingTest.php',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('pest')
            ->and($payload['status'])->toBe('passed')
            ->and($payload['summary'])->toBe([
                'tests' => 1,
                'passed' => 1,
                'failures' => 0,
                'errors' => 0,
                'skipped' => 0,
            ])
            ->and($payload['meta']['filter'])->toBeFalse()
            ->and($payload['meta']['coverage'])->toBeFalse()
            ->and($payload['meta']['exit_code'])->toBe(0)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes failing pest executions with testcase details', function (): void {
    $cwd = makeTempDirectory();

    try {
        createPestProject($cwd);
        createProxyToolBinary($cwd, 'pest', siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest');

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'pest',
            '--configuration',
            'phpunit.xml',
            'tests/FailingTest.php',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('pest')
            ->and($payload['status'])->toBe('failed')
            ->and($payload['summary']['tests'])->toBe(1)
            ->and($payload['summary']['failures'])->toBe(1)
            ->and($payload['items'])->toHaveCount(1)
            ->and($payload['items'][0]['type'])->toBe('failure')
            ->and($payload['items'][0]['test'])->toBe('it fails')
            ->and($payload['items'][0]['message'])->not->toBe('')
            ->and($payload['meta']['exit_code'])->toBe(1)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});
