<?php

declare(strict_types=1);

use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\JsonFile;
use Tests\Support\FixtureProject;

it('writes and reads JSON objects with stable formatting', function (): void {
    $project = FixtureProject::create();
    $path = $project->path('sift.json');

    (new JsonFile())->writeObject($path, [
        'url' => 'https://raw.githubusercontent.com/vitorsreis/sift/v2.0.0/resources/schema.json',
        'enabled' => true,
    ]);

    expect(file_get_contents($path))->toBe(implode("\n", [
        '{',
        '    "url": "https://raw.githubusercontent.com/vitorsreis/sift/v2.0.0/resources/schema.json",',
        '    "enabled": true',
        '}',
        '',
    ]));
    expect((new JsonFile())->readObject($path))->toBe([
        'url' => 'https://raw.githubusercontent.com/vitorsreis/sift/v2.0.0/resources/schema.json',
        'enabled' => true,
    ]);
});

it('rejects empty, invalid, or non-object JSON', function (string $contents, string $message): void {
    $project = FixtureProject::create();
    $path = $project->write('sift.json', $contents);

    expect(fn(): mixed => (new JsonFile())->readObject($path))
        ->toThrow(FilesystemException::class, $message);
})->with([
    ['', 'The JSON file is empty.'],
    ['[]', 'The JSON root must be an object.'],
    ['{', 'Syntax error'],
]);
