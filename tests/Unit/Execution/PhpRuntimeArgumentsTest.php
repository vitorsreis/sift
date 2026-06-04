<?php

declare(strict_types=1);

use Sift\Execution\PhpRuntimeArguments;

it('extracts php ini definitions before the script token', function (): void {
    $arguments = new PhpRuntimeArguments([
        PHP_BINARY,
        '-d',
        'memory_limit=512M',
        '-dxdebug.mode=off',
        '--some-php-flag',
        'bin/sift',
        'pest',
    ]);

    expect($arguments->arguments())->toBe([
        '-dmemory_limit=512M',
        '-dxdebug.mode=off',
    ]);
});

it('stops extracting php ini definitions at the first non-option token', function (): void {
    $arguments = new PhpRuntimeArguments([
        PHP_BINARY,
        'bin/sift',
        '-dxdebug.mode=coverage',
        'pest',
    ]);

    expect($arguments->arguments())->toBe([]);
});
