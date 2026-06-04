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

if (PHP_OS_FAMILY !== 'Windows') {
    it('preserves existing file permissions', function (): void {
        $project = FixtureProject::create();
        $path = $project->write('private.json', 'old');
        chmod($path, 0600);

        (new AtomicWriter())->write($path, 'new');

        expect(fileperms($path) & 0777)->toBe(0600);
    });

    it('creates files and directories with restricted permissions', function (): void {
        $project = FixtureProject::create();
        $path = $project->path('config/sift.json');

        (new AtomicWriter())->write($path, '{}');

        expect(fileperms($path) & 0777)->toBe(0644);
        expect(fileperms(dirname($path)) & 0777)->toBe(0755);
    });
}
