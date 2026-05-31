<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withParallel()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withFileExtensions(['php'])
    ->withRootFiles()
    ->withSets([LevelSetList::UP_TO_PHP_83])
    ->withIndent(indentChar: ' ', indentSize: 4)
    ->withAttributesSets(all: true)
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: false,
        removeUnusedImports: true,
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        naming: false,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: false,
        carbon: false,
        rectorPreset: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: false,
        symfonyCodeQuality: false,
        symfonyConfigs: false,
    );
