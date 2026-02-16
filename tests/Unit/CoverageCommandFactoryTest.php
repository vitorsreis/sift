<?php

declare(strict_types=1);

use Sift\Runtime\CoverageCommandFactory;

it('builds a sift coverage command when xdebug is loaded', function (): void {
    $factory = new CoverageCommandFactory(
        phpBinary: 'php',
        loadedExtensions: ['json', 'xdebug'],
        phpdbgBinary: null,
    );

    expect($factory->build(siftRoot()))->toBe([
        'php',
        siftRoot().DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'sift',
        'pest',
        '--coverage',
        '--min=80',
    ]);
});

it('builds a phpdbg coverage command when no extension driver is loaded', function (): void {
    $factory = new CoverageCommandFactory(
        phpBinary: 'php',
        loadedExtensions: ['json'],
        phpdbgBinary: 'phpdbg',
    );

    expect($factory->build(siftRoot()))->toBe([
        'phpdbg',
        '-qrr',
        siftRoot().DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'sift',
        'pest',
        '--coverage',
        '--min=80',
    ]);
});

it('fails with a clear message when no coverage driver is available', function (): void {
    $factory = new CoverageCommandFactory(
        phpBinary: 'php',
        loadedExtensions: ['json'],
        phpdbgBinary: null,
    );

    expect(fn () => $factory->build(siftRoot()))
        ->toThrow(RuntimeException::class, 'No PHP coverage driver is available.');
});
