<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\Composer\ComposerLicensesParser;

it('normalizes composer licenses dependencies and root package extra data', function (): void {
    $report = (new ComposerLicensesParser())->parse(composerLicensesParserJson(), '');

    expect($report->summary())->toMatchArray([
        'dependencies' => 2,
        'licenses' => [
            'BSD-3-Clause' => 1,
            'MIT' => 2,
        ],
    ]);
    expect($report->extra())->toBe([
        'root_package' => [
            'name' => 'vitorsreis/sift',
            'version' => 'dev-master',
            'licenses' => ['MIT'],
        ],
    ]);
    $console = composerLicensesParserItem($report->items(), 'symfony/console');

    expect($console)->toMatchArray([
        'type' => ItemType::License->value,
        'package' => 'symfony/console',
        'version' => 'v7.4.13',
        'licenses' => ['MIT'],
    ]);
});

/**
 * @param list<array<string, mixed>> $items
 *
 * @return array<string, mixed>
 */
function composerLicensesParserItem(array $items, string $package): array
{
    foreach ($items as $item) {
        if (($item['package'] ?? null) === $package) {
            return $item;
        }
    }

    throw new RuntimeException(sprintf('Expected package "%s" item.', $package));
}

function composerLicensesParserJson(): string
{
    return json_encode([
        'name' => 'vitorsreis/sift',
        'version' => 'dev-master',
        'license' => ['MIT'],
        'dependencies' => [
            'symfony/console' => [
                'version' => 'v7.4.13',
                'license' => ['MIT'],
            ],
            'phpunit/phpunit' => [
                'version' => '12.5.24',
                'license' => ['BSD-3-Clause', 'MIT'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
