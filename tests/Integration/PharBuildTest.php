<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('builds an executable phar distribution', function (): void {
    $root = siftRoot();
    $distDirectory = $root.DIRECTORY_SEPARATOR.'dist';
    $pharPath = $distDirectory.DIRECTORY_SEPARATOR.'sift.phar';
    $checksumPath = $pharPath.'.sha256';

    removeDirectory($distDirectory);

    $build = new Process(
        command: [PHP_BINARY, '-d', 'phar.readonly=0', $root.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'phar'],
        cwd: $root,
    );

    $build->run();

    expect($build->getExitCode())->toBe(0);

    $payload = json_decode(trim($build->getOutput()), true, flags: JSON_THROW_ON_ERROR);

    expect($payload)
        ->toMatchArray([
            'status' => 'built',
            'phar' => $pharPath,
            'sha256' => $checksumPath,
        ])
        ->and($pharPath)->toBeFile()
        ->and($checksumPath)->toBeFile();

    $archive = new Phar($pharPath);

    expect(isset($archive['vendor/autoload.php']))->toBeFalse();

    unset($archive);

    $process = new Process(
        command: [PHP_BINARY, $pharPath, 'help', '--format=json'],
        cwd: $distDirectory,
    );

    $process->run();

    expect($process->getExitCode())->toBe(0);

    $help = decodeJsonOutput($process);

    expect($help['status'])->toBe('ok')
        ->and($help['tool'])->toBe('sift')
        ->and($help['commands'])->toContain('help', 'view')
        ->and($help['options'])->toContain('--format=<json|markdown>');

    removeDirectory($distDirectory);
});
