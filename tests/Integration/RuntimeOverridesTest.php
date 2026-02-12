<?php

declare(strict_types=1);

it('applies no-history without writing a run id', function (): void {
    $process = runSift(['--no-history', '--size=fuller', '--format=json', '--pretty', 'pint']);

    expect($process->getExitCode())->toBe(0);

    $payload = decodeJsonOutput($process);

    expect(array_key_exists('run_id', $payload))->toBeFalse()
        ->and($payload['meta'])->toHaveKey('created_at');
});

it('disables tools through a custom config path', function (): void {
    $cwd = makeTempDirectory();

    try {
        writeSiftConfig($cwd, [
            'history' => ['enabled' => true],
            'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => true],
            'tools' => ['pint' => ['enabled' => false]],
        ], 'custom.sift.json');

        $process = runSift(['--config=custom.sift.json', 'pint'], $cwd);

        expect($process->getExitCode())->toBe(1);

        $payload = decodeJsonOutput($process);

        expect($payload['error']['code'])->toBe('tool_disabled');
    } finally {
        removeDirectory($cwd);
    }
});

it('uses configured tool binaries during execution', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProxyToolBinary($cwd, 'pint-custom', siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pint');
        file_put_contents($cwd.DIRECTORY_SEPARATOR.'Good.php', "<?php\n\ndeclare(strict_types=1);\n");
        writeSiftConfig($cwd, [
            'history' => ['enabled' => true],
            'output' => ['format' => 'json', 'size' => 'fuller', 'pretty' => true],
            'tools' => [
                'pint' => [
                    'enabled' => true,
                    'toolBinary' => 'vendor/bin/pint-custom',
                ],
            ],
        ]);

        $process = runSift(['--no-history', 'pint', $cwd], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['status'])->toBe('passed');
    } finally {
        removeDirectory($cwd);
    }
});

it('blocks configured native arguments before execution', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProxyToolBinary($cwd, 'pint-custom', siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pint');
        writeSiftConfig($cwd, [
            'history' => ['enabled' => true],
            'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => true],
            'tools' => [
                'pint' => [
                    'enabled' => true,
                    'toolBinary' => 'vendor/bin/pint-custom',
                    'blockedArgs' => ['--dirty'],
                ],
            ],
        ]);

        $process = runSift(['pint', '--dirty'], $cwd);

        expect($process->getExitCode())->toBe(1);

        $payload = decodeJsonOutput($process);

        expect($payload['error']['code'])->toBe('blocked_argument')
            ->and($payload['error']['tool'])->toBe('pint')
            ->and($payload['error']['argument'])->toBe('--dirty');
    } finally {
        removeDirectory($cwd);
    }
});
