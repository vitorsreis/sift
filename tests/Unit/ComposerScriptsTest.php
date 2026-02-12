<?php

declare(strict_types=1);

it('routes composer test scripts through sift pest', function (): void {
    /** @var array{scripts: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['scripts'])->toMatchArray([
        'test' => '@php bin/sift pest',
        'test:unit' => '@php bin/sift pest --testsuite=Unit',
        'test:integration' => '@php bin/sift pest --testsuite=Integration',
    ]);
});
