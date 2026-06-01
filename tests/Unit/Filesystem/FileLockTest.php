<?php

declare(strict_types=1);

use Sift\Filesystem\FileLock;
use Sift\Filesystem\FilesystemException;
use Tests\Support\FixtureProject;

it('holds and releases an exclusive file lock', function (): void {
    $project = FixtureProject::create();
    $path = $project->write('.sift/locks/skills/generic.lock', '');

    $lock = FileLock::acquire($path);
    $handle = fopen($path, 'c+b');

    if (! is_resource($handle)) {
        throw new RuntimeException('Could not open lock fixture.');
    }

    expect(flock($handle, LOCK_EX | LOCK_NB))->toBeFalse();

    $lock->release();

    expect(flock($handle, LOCK_EX | LOCK_NB))->toBeTrue();

    flock($handle, LOCK_UN);
    fclose($handle);
});

it('fails when the file lock is already held', function (): void {
    $project = FixtureProject::create();
    $path = $project->write('.sift/locks/skills/generic.lock', '');
    $handle = fopen($path, 'c+b');

    if (! is_resource($handle)) {
        throw new RuntimeException('Could not open lock fixture.');
    }

    flock($handle, LOCK_EX);

    try {
        FileLock::acquire($path);
    } catch (FilesystemException $filesystemException) {
        flock($handle, LOCK_UN);
        fclose($handle);

        expect($filesystemException->getMessage())->toContain('Could not acquire lock file');

        return;
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    throw new RuntimeException('Expected lock failure.');
});
