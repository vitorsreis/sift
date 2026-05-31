<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\ComposerUnused\ComposerUnusedParser;

it('normalizes composer-unused package lists and optional reasons', function (): void {
    $report = (new ComposerUnusedParser())->parse(json_encode([
        'used-packages' => [
            ['name' => 'php'],
            ['name' => 'ext-json', 'required-by' => ['vitorsreis/sift']],
        ],
        'unused-packages' => [
            'vimeo/psalm',
            [
                'name' => 'phpmd/phpmd',
                'path' => 'composer.json',
                'reason' => 'No referenced symbols were found.',
            ],
        ],
        'ignored-packages' => ['composer-plugin-api'],
        'zombie-exclusions' => ['symfony/*'],
    ], JSON_THROW_ON_ERROR), '');

    expect($report->unusedPackages())->toBe(2);
    expect($report->findings())->toBe(3);
    expect($report->summary())->toBe([
        'used_packages' => 2,
        'unused_packages' => 2,
        'ignored_packages' => 1,
        'zombie_exclusions' => 1,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::UnusedDependency->value,
            'package' => 'vimeo/psalm',
        ],
        [
            'type' => ItemType::UnusedDependency->value,
            'package' => 'phpmd/phpmd',
            'path' => 'composer.json',
            'reason' => 'No referenced symbols were found.',
        ],
        [
            'type' => ItemType::Warning->value,
            'message' => 'Unused composer-unused exclusion.',
            'exclusion' => 'symfony/*',
        ],
    ]);
});
