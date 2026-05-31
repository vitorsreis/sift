<?php

declare(strict_types=1);

use Sift\Filesystem\TempFile;
use Tests\Support\FixtureProject;

it('tracks and removes a temporary file', function (): void {
    $project = FixtureProject::create();
    $path = $project->path('tmp/output.xml');
    $project->write('tmp/output.xml', '<testsuite />');

    $tempFile = new TempFile($path);

    expect($tempFile->path())->toBe($path);
    expect($tempFile->exists())->toBeTrue();

    $tempFile->remove();

    expect(is_file($path))->toBeFalse();
    expect($tempFile->exists())->toBeFalse();
});

it('removes temporary files idempotently', function (): void {
    $project = FixtureProject::create();
    $path = $project->write('tmp/report.json', '{}');
    $tempFile = new TempFile($path);

    $tempFile->remove();
    $tempFile->remove();

    expect(is_file($path))->toBeFalse();
});

it('rejects empty temporary file paths', function (): void {
    expect(fn(): mixed => new TempFile(''))
        ->toThrow(InvalidArgumentException::class, 'Temporary file path cannot be empty.');
});
