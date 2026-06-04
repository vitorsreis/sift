<?php

declare(strict_types=1);

use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\SkillsShCatalogClient;

it('queries the public catalog with encoded query limit timeout and no auth header', function (): void {
    putenv('SKILLS_SH_API_KEY=secret');
    /** @var list<array{url: string, timeout: int, headers: list<string>}> $calls */
    $calls = [];
    $client = new SkillsShCatalogClient(
        fetcher: function (string $url, int $timeout, array $headers) use (&$calls): array {
            $calls[] = [
                'url' => $url,
                'timeout' => $timeout,
                'headers' => $headers,
            ];

            return [
                'status' => 200,
                'body' => '{"items":[]}',
                'error' => null,
            ];
        },
        baseUrl: 'https://skills.sh/api/search',
    );

    expect($client->search('php review'))->toBe(['items' => []]);
    expect($calls[0]['url'] ?? null)->toBe('https://skills.sh/api/search?q=php+review&limit=10');
    expect($calls[0]['timeout'] ?? null)->toBe(10);
    expect($calls[0]['headers'] ?? [])->toContain('Accept: application/json');
    expect(implode("\n", $calls[0]['headers'] ?? []))->not->toContain('Authorization');

    putenv('SKILLS_SH_API_KEY');
});

it('uses SKILLS_API_URL as the self hosted catalog endpoint', function (): void {
    putenv('SKILLS_API_URL=https://catalog.example.test/search');
    /** @var list<string> $urls */
    $urls = [];
    $client = new SkillsShCatalogClient(
        fetcher: function (string $url, int $timeout, array $headers) use (&$urls): array {
            $urls[] = $url;

            return [
                'status' => 200,
                'body' => '{"items":[]}',
                'error' => null,
            ];
        },
    );

    $client->search('review');

    expect($urls)->toBe(['https://catalog.example.test/search?q=review&limit=10']);

    putenv('SKILLS_API_URL');
});

it('appends query parameters to catalog endpoints that already have parameters', function (): void {
    /** @var list<string> $urls */
    $urls = [];
    $client = new SkillsShCatalogClient(
        fetcher: function (string $url, int $timeout, array $headers) use (&$urls): array {
            $urls[] = $url;

            return [
                'status' => 200,
                'body' => '{"items":[]}',
                'error' => null,
            ];
        },
        baseUrl: 'https://catalog.example.test/search?source=sift',
        timeoutSeconds: 3,
    );

    $client->search('php review', 5);

    expect($urls)->toBe(['https://catalog.example.test/search?source=sift&q=php+review&limit=5']);
});

it('rejects invalid catalog search input before fetching', function (string $query, int $limit, string $message): void {
    $called = false;
    $client = new SkillsShCatalogClient(
        fetcher: function () use (&$called): array {
            $called = true;

            return [
                'status' => 200,
                'body' => '{"items":[]}',
                'error' => null,
            ];
        },
    );

    expect(fn(): array => $client->search($query, $limit))->toThrow(InvalidUsageException::class, $message);
    expect($called)->toBeFalse();
})->with([
    'empty query' => ['   ', 10, 'skills find requires a query.'],
    'zero limit' => ['review', 0, 'skills find limit must be positive.'],
]);

it('requires a positive catalog timeout', function (): void {
    expect(fn(): SkillsShCatalogClient => new SkillsShCatalogClient(timeoutSeconds: 0))
        ->toThrow(InvalidArgumentException::class, 'Catalog timeout must be positive.');
});

it('returns skill catalog unavailable for failed catalog responses', function (): void {
    /** @var list<array{status: int|null, body: string|null, error: string|null}> $responses */
    $responses = [
        ['status' => null, 'body' => null, 'error' => 'network'],
        ['status' => null, 'body' => null, 'error' => 'timeout'],
        ['status' => 500, 'body' => '{}', 'error' => null],
        ['status' => 200, 'body' => '{', 'error' => null],
        ['status' => 200, 'body' => '"unexpected"', 'error' => null],
    ];

    foreach ($responses as $response) {
        $client = new SkillsShCatalogClient(
            fetcher: static fn(string $url, int $timeout, array $headers): array => $response,
            baseUrl: 'https://skills.sh/api/search',
        );

        try {
            $client->search('review');
        } catch (UserFacingException $userFacingException) {
            expect($userFacingException->errorCode())->toBe(ErrorCode::SkillCatalogUnavailable);

            continue;
        }

        throw new RuntimeException('Expected catalog client failure.');
    }
});
