<?php

declare(strict_types=1);

use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\PathGuard;
use Tests\Support\FixtureProject;

it('allows paths inside the configured root', function (): void {
    $project = FixtureProject::create();
    $guard = new PathGuard($project->root());

    expect($guard->assertInside($project->path('config/sift.json')))->toBe($project->path('config/sift.json'));
});

it('rejects paths that escape the configured root', function (): void {
    $project = FixtureProject::create();
    $outside = FixtureProject::create();
    $guard = new PathGuard($project->root());

    expect(fn(): mixed => $guard->assertInside($outside->path('sift.json')))
        ->toThrow(FilesystemException::class, 'Path escapes the allowed root.');

    expect(fn(): mixed => $guard->assertInside($project->path('../outside.json')))
        ->toThrow(FilesystemException::class, 'Path escapes the allowed root.');
});

if (PHP_OS_FAMILY !== 'Windows') {
    it('resolves existing symlinks before allowing writes', function (): void {
        $project = FixtureProject::create();
        $outside = FixtureProject::create();
        $project->mkdir('links');
        $outside->write('target.json', '{}');

        if (! @symlink($outside->path('target.json'), $project->path('links/target.json'))) {
            return;
        }

        $guard = new PathGuard($project->root());

        expect(fn(): mixed => $guard->assertInside($project->path('links/target.json')))
            ->toThrow(FilesystemException::class, 'Path escapes the allowed root.');
    });
}
