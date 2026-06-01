<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\Composer\ComposerPackagesParser;

it('normalizes composer package lists with outdated and abandoned metadata', function (): void {
    $report = (new ComposerPackagesParser())->parse(composerPackagesParserJson(), '');

    expect($report->summary())->toMatchArray([
        'packages' => 3,
        'direct_dependencies' => 2,
        'outdated' => 1,
        'abandoned_packages' => 1,
        'findings' => 2,
    ]);
    expect($report->findings())->toBe(2);

    $phpunit = composerPackagesParserItem($report->items(), 'phpunit/phpunit');
    $psl = composerPackagesParserItem($report->items(), 'azjezz/psl');

    expect($phpunit)->toMatchArray([
        'type' => ItemType::Package->value,
        'package' => 'phpunit/phpunit',
        'version' => '12.5.24',
        'latest' => '12.5.28',
        'latest_status' => 'semver-safe-update',
        'direct_dependency' => true,
        'abandoned' => false,
        'description' => 'The PHP Unit Testing framework.',
    ]);
    expect($psl)->toMatchArray([
        'type' => ItemType::Package->value,
        'package' => 'azjezz/psl',
        'version' => '4.3.0',
        'latest' => '4.3.0',
        'latest_status' => 'up-to-date',
        'direct_dependency' => false,
        'abandoned' => true,
        'replacement' => 'php-standard-library/php-standard-library',
        'description' => 'PHP Standard Library',
    ]);
});

/**
 * @param list<array<string, mixed>> $items
 *
 * @return array<string, mixed>
 */
function composerPackagesParserItem(array $items, string $package): array
{
    foreach ($items as $item) {
        if (($item['package'] ?? null) === $package) {
            return $item;
        }
    }

    throw new RuntimeException(sprintf('Expected package "%s" item.', $package));
}

function composerPackagesParserJson(): string
{
    return json_encode([
        'installed' => [
            [
                'name' => 'phpunit/phpunit',
                'version' => '12.5.24',
                'latest' => '12.5.28',
                'latest-status' => 'semver-safe-update',
                'direct-dependency' => true,
                'description' => 'The PHP Unit Testing framework.',
                'abandoned' => false,
            ],
            [
                'name' => 'azjezz/psl',
                'version' => '4.3.0',
                'latest' => '4.3.0',
                'latest-status' => 'up-to-date',
                'direct-dependency' => false,
                'description' => 'PHP Standard Library',
                'abandoned' => 'php-standard-library/php-standard-library',
            ],
            [
                'name' => 'symfony/console',
                'version' => 'v7.4.13',
                'direct-dependency' => true,
                'abandoned' => false,
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
