<?php

declare(strict_types=1);

it('adds a detected tool to a fresh sift config', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'phpstan.bat', "@echo off\r\n");

        $process = runSift(['add', 'phpstan', '--format=json', '--pretty'], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);
        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'sift.json'), true, flags: JSON_THROW_ON_ERROR);
        $detectedPath = str_replace('\\', '/', (string) $payload['detected']['path']);

        expect($payload['status'])->toBe('added')
            ->and($payload['tool'])->toBe('phpstan')
            ->and($payload['config_created'])->toBeTrue()
            ->and($detectedPath)->toEndWith('vendor/bin/phpstan.bat')
            ->and($config['tools']['phpstan'])->toBe([
                'enabled' => true,
                'defaultArgs' => ['analyse'],
            ]);
    } finally {
        removeDirectory($cwd);
    }
});

it('merges a tool into an existing custom config file', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'pint.bat', "@echo off\r\n");
        writeSiftConfig($cwd, [
            'history' => [
                'enabled' => true,
            ],
            'output' => [
                'format' => 'json',
                'size' => 'normal',
            ],
            'tools' => [
                'phpstan' => [
                    'enabled' => false,
                    'defaultArgs' => ['analyse', 'src'],
                ],
            ],
        ], 'custom.sift.json');

        $process = runSift(['add', 'pint', '--config=custom.sift.json', '--format=json', '--pretty'], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);
        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'custom.sift.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($payload['status'])->toBe('added')
            ->and($payload['config_created'])->toBeFalse()
            ->and(array_keys($config['tools']))->toBe(['phpstan', 'pint'])
            ->and($config['tools']['pint'])->toBe([
                'enabled' => true,
                'defaultArgs' => ['--test'],
            ]);
    } finally {
        removeDirectory($cwd);
    }
});

it('reports when adding a tool that is not detected in the project', function (): void {
    $cwd = makeTempDirectory();

    try {
        $process = runSift(['add', 'phpunit', '--format=json', '--pretty'], $cwd, [
            'PATH' => '',
            'Path' => '',
        ]);

        expect($process->getExitCode())->toBe(1);

        $payload = decodeJsonOutput($process);

        expect($payload['status'])->toBe('error')
            ->and($payload['error']['code'])->toBe('tool_not_installed')
            ->and($payload['error']['tool'])->toBe('phpunit');
    } finally {
        removeDirectory($cwd);
    }
});
