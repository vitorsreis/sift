<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;

it('carries the resolved command passed to policies and process runners', function (): void {
    $command = new PreparedCommand(
        tool: 'pest',
        binary: 'vendor/bin/pest',
        arguments: ['--compact', '--colors=never'],
        cwd: '/project',
        environment: ['XDEBUG_MODE' => 'off'],
        timeout: 120,
    );

    expect($command->tool())->toBe('pest');
    expect($command->binary())->toBe('vendor/bin/pest');
    expect($command->arguments())->toBe(['--compact', '--colors=never']);
    expect($command->argv())->toBe(['vendor/bin/pest', '--compact', '--colors=never']);
    expect($command->cwd())->toBe('/project');
    expect($command->environment())->toBe(['XDEBUG_MODE' => 'off']);
    expect($command->timeout())->toBe(120);
});

it('carries generated files, display command and native output mode', function (): void {
    $command = new PreparedCommand(
        tool: 'pest',
        binary: 'vendor/bin/pest',
        arguments: ['--log-junit', 'build/junit.xml'],
        temporaryFiles: ['C:/tmp/sift-junit.xml'],
        artifacts: ['junit' => 'C:/tmp/sift-junit.xml'],
        displayCommand: ['vendor/bin/pest', '--log-junit', '<temp>'],
        nativeOutputMode: 'inherit',
    );

    expect($command->temporaryFiles())->toBe(['C:/tmp/sift-junit.xml']);
    expect($command->artifacts())->toBe(['junit' => 'C:/tmp/sift-junit.xml']);
    expect($command->displayCommand())->toBe(['vendor/bin/pest', '--log-junit', '<temp>']);
    expect($command->nativeOutputMode())->toBe('inherit');
});

it('rejects an empty resolved binary', function (): void {
    expect(fn(): PreparedCommand => new PreparedCommand(
        tool: 'pest',
        binary: '',
    ))->toThrow(InvalidArgumentException::class, 'Prepared command binary cannot be empty.');
});
