<?php

declare(strict_types=1);

it('routes composer scripts through sift commands', function (): void {
    /** @var array{scripts: array<string, mixed>} $composer */
    $composer = json_decode((string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['scripts'])->toMatchArray([
        'sift' => '@php bin/sift',
        'lint' => '@sift pint',
        'test' => '@sift pest --parallel',
        'test:unit' => '@test --testsuite=Unit',
        'test:integration' => '@test --testsuite=Integration',
        'test:coverage' => [
            '@php -r "is_dir(\'build/coverage\') || mkdir(\'build/coverage\', 0777, true);"',
            '@php -d xdebug.mode=coverage bin/sift --raw pest --parallel --coverage --min=80 --coverage-clover build/coverage/clover.xml',
        ],
    ]);
});

it('defines a dedicated phar build script', function (): void {
    /** @var array{scripts: array<string, mixed>} $composer */
    $composer = json_decode((string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['scripts'])
        ->toHaveKey('build:phar')
        ->and($composer['scripts']['build:phar'])->toBe([
            'Composer\\Config::disableProcessTimeout',
            '@php -d phar.readonly=0 bin/phar',
        ]);
});
