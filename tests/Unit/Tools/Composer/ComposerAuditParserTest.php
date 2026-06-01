<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\Composer\ComposerAuditParser;

it('normalizes composer audit advisories and abandoned packages', function (): void {
    $report = (new ComposerAuditParser())->parse(composerAuditParserJson(), '');

    expect($report->summary())->toMatchArray([
        'advisories' => 2,
        'vulnerable_packages' => 1,
        'abandoned_packages' => 1,
        'findings' => 3,
    ]);
    expect($report->findings())->toBe(3);
    expect($report->items())->toContain([
        'type' => ItemType::Vulnerability->value,
        'package' => 'symfony/http-foundation',
        'advisory_id' => 'PKSA-test',
        'cve' => 'CVE-2026-0001',
        'title' => 'Header injection',
        'link' => 'https://example.com/advisory',
        'affected_versions' => '<6.4.1',
        'reported_at' => '2026-01-10T00:00:00+00:00',
    ]);
    $abandoned = composerAuditParserItem($report->items(), 'azjezz/psl');

    expect($abandoned)->toMatchArray([
        'type' => ItemType::Package->value,
        'package' => 'azjezz/psl',
        'abandoned' => true,
        'replacement' => 'php-standard-library/php-standard-library',
        'message' => 'Package is abandoned.',
    ]);
});

/**
 * @param list<array<string, mixed>> $items
 *
 * @return array<string, mixed>
 */
function composerAuditParserItem(array $items, string $package): array
{
    foreach ($items as $item) {
        if (($item['package'] ?? null) === $package) {
            return $item;
        }
    }

    throw new RuntimeException(sprintf('Expected package "%s" item.', $package));
}

function composerAuditParserJson(): string
{
    return json_encode([
        'advisories' => [
            'symfony/http-foundation' => [
                [
                    'advisoryId' => 'PKSA-test',
                    'packageName' => 'symfony/http-foundation',
                    'cve' => 'CVE-2026-0001',
                    'title' => 'Header injection',
                    'link' => 'https://example.com/advisory',
                    'affectedVersions' => '<6.4.1',
                    'reportedAt' => '2026-01-10T00:00:00+00:00',
                ],
                [
                    'advisoryId' => 'PKSA-second',
                    'title' => 'Second advisory',
                    'affectedVersions' => '<6.4.2',
                ],
            ],
        ],
        'abandoned' => [
            'azjezz/psl' => 'php-standard-library/php-standard-library',
        ],
    ], JSON_THROW_ON_ERROR);
}
