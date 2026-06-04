<?php

declare(strict_types=1);

use Composer\Command\BaseCommand;
use Sift\Core\ItemType;
use Sift\Tools\ComposerRequireChecker\ComposerRequireCheckerParser;

it('normalizes composer-require-checker unknown symbols and packages', function (): void {
    $report = (new ComposerRequireCheckerParser())->parse(json_encode([
        '_meta' => [
            'composer-require-checker' => ['version' => '4.20.0'],
        ],
        'unknown-symbols' => [
            BaseCommand::class => [],
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

    expect($report->unknownSymbols())->toBe(3);
    expect($report->summary())->toBe([
        'unknown_symbols' => 3,
        'packages' => 2,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::MissingDependency->value,
            'symbol' => BaseCommand::class,
            'packages' => [],
        ],
        [
            'type' => ItemType::MissingDependency->value,
            'symbol' => 'ctype_digit',
            'packages' => ['ext-ctype'],
            'package' => 'ext-ctype',
        ],
        [
            'type' => ItemType::MissingDependency->value,
            'symbol' => 'App\\Ghost',
            'packages' => ['vendor/missing'],
            'package' => 'vendor/missing',
            'file' => 'src/Ghost.php',
            'line' => 12,
        ],
    ]);
});
