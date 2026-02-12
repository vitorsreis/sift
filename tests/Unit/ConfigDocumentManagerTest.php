<?php

declare(strict_types=1);

use Sift\Runtime\ConfigDocumentManager;
use Sift\Runtime\ConfigLoader;

it('writes config documents with two-space indentation', function (): void {
    $cwd = makeTempDirectory();

    try {
        $manager = new ConfigDocumentManager(new ConfigLoader);
        $manager->write($cwd, [
            'history' => [
                'enabled' => true,
            ],
            'output' => [
                'format' => 'json',
                'size' => 'normal',
            ],
            'tools' => [
                'phpstan' => [
                    'enabled' => true,
                    'defaultArgs' => ['analyse'],
                ],
            ],
        ]);

        $raw = (string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'sift.json');

        expect($raw)->toContain("\n  \"history\": {")
            ->and($raw)->toContain("\n    \"enabled\": true")
            ->and($raw)->not->toContain("\n    \"history\": {");
    } finally {
        removeDirectory($cwd);
    }
});

it('returns a default config document when the file does not exist', function (): void {
    $cwd = makeTempDirectory();

    try {
        $document = (new ConfigDocumentManager(new ConfigLoader))->readOrDefault($cwd);

        expect($document)->toBe([
            'history' => [
                'enabled' => true,
            ],
            'output' => [
                'format' => 'json',
                'size' => 'normal',
            ],
            'tools' => [],
        ]);
    } finally {
        removeDirectory($cwd);
    }
});
