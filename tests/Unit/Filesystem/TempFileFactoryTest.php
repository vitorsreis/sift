<?php

declare(strict_types=1);

use Sift\Filesystem\TempFile;
use Sift\Filesystem\TempFileFactory;
use Tests\Support\FixtureProject;

it('creates and removes temporary files', function (): void {
    $project = FixtureProject::create();
    $factory = new TempFileFactory($project->path('tmp'));
    $tempFile = $factory->create('coverage-', '.xml');

    expect($tempFile)->toBeInstanceOf(TempFile::class);
    expect($tempFile->exists())->toBeTrue();
    expect(basename($tempFile->path()))->toStartWith('coverage-');
    expect($tempFile->path())->toEndWith('.xml');

    $tempFile->remove();

    expect($tempFile->exists())->toBeFalse();
});

it('rejects empty temporary paths', function (): void {
    expect(fn(): mixed => new TempFileFactory(' '))
        ->toThrow(InvalidArgumentException::class, 'Temporary directory cannot be empty.');

    expect(fn(): mixed => new TempFile(' '))
        ->toThrow(InvalidArgumentException::class, 'Temporary file path cannot be empty.');
});
