<?php

declare(strict_types=1);

use Sift\Runtime\CoverageCommandFactory;

it('builds a sift coverage command when xdebug is loaded', function (): void {
    $factory = new CoverageCommandFactory(
        phpBinary: 'php',
        loadedExtensions: ['json', 'xdebug'],
    );

    expect($factory->build(siftRoot()))->toBe([
        'php',
        siftRoot().DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'sift',
        'pest',
        '--coverage',
        '--min=80',
        '--coverage-clover',
        siftRoot().DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'coverage'.DIRECTORY_SEPARATOR.'clover.xml',
    ]);
});

it('fails when only phpdbg is available without a native coverage extension', function (): void {
    $factory = new CoverageCommandFactory(
        phpBinary: 'php',
        loadedExtensions: ['json'],
    );

    expect(fn () => $factory->build(siftRoot()))
        ->toThrow(RuntimeException::class, 'No PHP coverage driver is available.');
});

it('fails with a clear message when no coverage driver is available', function (): void {
    $factory = new CoverageCommandFactory(
        phpBinary: 'php',
        loadedExtensions: ['json'],
    );

    expect(fn () => $factory->build(siftRoot()))
        ->toThrow(RuntimeException::class, 'No PHP coverage driver is available.');
});
