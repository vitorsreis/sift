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
