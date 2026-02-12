<?php

declare(strict_types=1);

it('renders help with the current cli options', function (): void {
    $process = runSift(['help', '--format=json', '--pretty']);

    expect($process->getExitCode())->toBe(0);

    $payload = decodeJsonOutput($process);

    expect($payload['commands'])->toContain('add', 'view')
        ->and($payload['options'])->toContain('--config=<path>', '--no-history');
});

it('initializes and validates a custom config path after the command name', function (): void {
    $cwd = makeTempDirectory();

    try {
        $init = runSift(['init', '--config=custom.sift.json', '--force', '--format=json', '--pretty'], $cwd);
        $validate = runSift(['validate', '--config=custom.sift.json', '--format=json', '--pretty'], $cwd);

        expect($init->getExitCode())->toBe(0)
            ->and($validate->getExitCode())->toBe(0)
            ->and(is_file($cwd.DIRECTORY_SEPARATOR.'custom.sift.json'))->toBeTrue();

        $payload = decodeJsonOutput($validate);
        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'custom.sift.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($payload['status'])->toBe('valid')
            ->and($payload['path'])->toEndWith('custom.sift.json')
            ->and($config['$schema'])->toBe('./resources/schema/config.schema.json');
    } finally {
        removeDirectory($cwd);
    }
});

it('writes detected tool binaries into new init configs', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'pint.bat', "@echo off\r\n");

        $init = runSift(['init', '--force', '--format=json', '--pretty'], $cwd);

        expect($init->getExitCode())->toBe(0);

        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'sift.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($config['tools']['pint'])->toBe([
            'enabled' => true,
            'defaultArgs' => ['--test'],
            'toolBinary' => 'vendor/bin/pint.bat',
        ]);
    } finally {
        removeDirectory($cwd);
    }
});

it('clears run history through the view command', function (): void {
    $cwd = makeTempDirectory();

    try {
        writeJsonFile($cwd.DIRECTORY_SEPARATOR.'.sift'.DIRECTORY_SEPARATOR.'history'.DIRECTORY_SEPARATOR.'deadbeef.json', [
            'created_at' => 1234567890,
            'result' => [
                'tool' => 'pint',
                'status' => 'failed',
                'summary' => ['files' => 1],
                'items' => [],
                'artifacts' => [],
                'extra' => [],
                'meta' => [],
            ],
        ]);

        $process = runSift(['view', '--clear', '--format=json', '--pretty'], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['status'])->toBe('cleared')
            ->and($payload['deleted'])->toBe(1)
            ->and(is_dir($cwd.DIRECTORY_SEPARATOR.'.sift'.DIRECTORY_SEPARATOR.'history'))->toBeFalse();
    } finally {
        removeDirectory($cwd);
    }
});
