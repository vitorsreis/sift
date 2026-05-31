<?php

declare(strict_types=1);

use Sift\Filesystem\AtomicWriter;
use Tests\Support\FixtureProject;

it('writes files through a temporary file in the same directory', function (): void {
    $project = FixtureProject::create();
    $path = $project->path('config/sift.json');

    (new AtomicWriter())->write($path, '{"status":"ok"}' . PHP_EOL);

    expect(file_get_contents($path))->toBe('{"status":"ok"}' . PHP_EOL);
    expect(glob($project->path('config/sift.json.tmp.*')))->toBe([]);
});

it('replaces existing files without leaving temporary files behind', function (): void {
    $project = FixtureProject::create();
    $project->write('sift.json', 'old');

    (new AtomicWriter())->write($project->path('sift.json'), 'new');

    expect(file_get_contents($project->path('sift.json')))->toBe('new');
    expect(glob($project->path('sift.json.tmp.*')))->toBe([]);
});
