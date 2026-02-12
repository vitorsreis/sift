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

        expect($raw)->toContain("\n  \"\$schema\": \"./resources/schema/config.schema.json\",")
            ->and($raw)->toContain("\n  \"history\": {")
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
            '$schema' => './resources/schema/config.schema.json',
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

it('ships a valid json schema for the sift config', function (): void {
    $path = siftRoot().DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.'config.schema.json';
    $schema = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect(is_file($path))->toBeTrue()
        ->and($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKeys(['history', 'output', 'tools'])
        ->and($schema['properties']['tools']['additionalProperties']['properties'])->toHaveKeys([
            'enabled',
            'toolBinary',
            'defaultArgs',
            'blockedArgs',
        ]);
});
