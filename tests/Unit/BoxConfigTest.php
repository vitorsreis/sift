<?php

declare(strict_types=1);

it('ships a box config aligned with the thin phar distribution', function (): void {
    /** @var array<string, mixed> $box */
    $box = json_decode((string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'box.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($box)->toMatchArray([
        'main' => 'resources/box.stub.php',
        'output' => 'dist/sift.phar',
        'alias' => 'sift.phar',
        'stub' => true,
        'compression' => 'NONE',
    ])->and($box['directories'])->toContain('src', 'resources')
        ->and($box['files'])->toContain('composer.json', 'README.md', 'LICENSE.md')
        ->and($box['exclude'])->toContain('vendor', 'tests', 'dist');
});
