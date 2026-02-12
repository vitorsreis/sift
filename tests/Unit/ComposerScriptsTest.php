<?php

declare(strict_types=1);

it('routes composer scripts through sift commands', function (): void {
    /** @var array{scripts: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['scripts'])->toMatchArray([
        'sift' => '@php bin/sift',
        'lint' => '@sift pint',
        'test' => '@sift pest',
        'test:unit' => '@sift pest --testsuite=Unit',
        'test:integration' => '@sift pest --testsuite=Integration',
    ]);
});
