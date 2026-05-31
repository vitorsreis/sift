<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\ComposerRequireChecker\ComposerRequireCheckerParser;

it('normalizes composer-require-checker unknown symbols and packages', function (): void {
    $report = (new ComposerRequireCheckerParser())->parse(json_encode([
        '_meta' => [
            'composer-require-checker' => ['version' => '4.20.0'],
        ],
        'unknown-symbols' => [
            'ctype_digit' => ['ext-ctype'],
            'App\\Ghost' => [
                [
                    'package' => 'vendor/missing',
                    'file' => 'src/Ghost.php',
                    'line' => 12,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR), '');

    expect($report->unknownSymbols())->toBe(2);
    expect($report->summary())->toBe([
        'unknown_symbols' => 2,
        'packages' => 2,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::MissingDependency->value,
            'symbol' => 'ctype_digit',
            'package' => 'ext-ctype',
            'packages' => ['ext-ctype'],
        ],
        [
            'type' => ItemType::MissingDependency->value,
            'symbol' => 'App\\Ghost',
            'package' => 'vendor/missing',
            'packages' => ['vendor/missing'],
            'file' => 'src/Ghost.php',
            'line' => 12,
        ],
    ]);
});
