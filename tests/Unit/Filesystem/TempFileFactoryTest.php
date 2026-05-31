<?php

declare(strict_types=1);

use Sift\Filesystem\TempFileFactory;
use Tests\Support\FixtureProject;

it('creates tracked temporary files in the configured directory', function (): void {
    $project = FixtureProject::create();
    $directory = $project->mkdir('tmp');

    $tempFile = (new TempFileFactory($directory))->create('junit-', '.xml');

    expect($tempFile->exists())->toBeTrue();
    expect(dirname($tempFile->path()))->toBe($directory);
    expect(basename($tempFile->path()))->toStartWith('junit-');
    expect($tempFile->path())->toEndWith('.xml');

    $tempFile->remove();
});

it('creates the configured temp directory when missing', function (): void {
    $project = FixtureProject::create();
    $directory = $project->path('missing/tmp');

    $tempFile = (new TempFileFactory($directory))->create('sift-', '.tmp');

    expect(is_dir($directory))->toBeTrue();
    expect($tempFile->exists())->toBeTrue();

    $tempFile->remove();
});

it('rejects empty temp directories', function (): void {
    expect(fn(): mixed => new TempFileFactory(''))
        ->toThrow(InvalidArgumentException::class, 'Temporary directory cannot be empty.');
});
