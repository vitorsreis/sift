<?php

declare(strict_types=1);

use Sift\Console\ResourcePathResolver;

it('resolves bundled resources from the source tree', function (): void {
    $resolver = ResourcePathResolver::fromProjectRoot(dirname(__DIR__, 3));

    expect($resolver->resource('schema.json'))->toBeFile();
    expect($resolver->resource('schema.json'))->toEndWith('resources' . DIRECTORY_SEPARATOR . 'schema.json');
    expect($resolver->skill('sift/SKILL.md'))->toBeFile();
    expect($resolver->skill('sift/SKILL.md'))->toEndWith('skills' . DIRECTORY_SEPARATOR . 'sift' . DIRECTORY_SEPARATOR . 'SKILL.md');
});
