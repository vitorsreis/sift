<?php

declare(strict_types=1);

use Sift\Console\CommandRoute;
use Sift\Console\Commands\SkillsFindCommand;
use Sift\Console\InteractivePrompt;
use Sift\Skills\SkillCatalog;
use Sift\Skills\SkillsShCatalogClient;

it('renders normalized skill catalog search results', function (): void {
    $command = new SkillsFindCommand(new SkillCatalog(new SkillsShCatalogClient(
        fetcher: static fn(string $url, int $timeout, array $headers): array => [
            'status' => 200,
            'body' => json_encode([
                'items' => [
                    [
                        'name' => 'php-review',
                        'description' => 'Review PHP projects.',
                        'source' => 'vitorsreis/sift',
                        'skills' => ['php-review'],
                        'agents' => ['codex'],
                        'tags' => ['php'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'error' => null,
        ],
    )));

    $payload = $command->handle(new CommandRoute('skills.find', ['php review']), __DIR__);

    expect($payload)->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'total' => 1,
        ],
        'items' => [
            [
                'name' => 'php-review',
                'description' => 'Review PHP projects.',
                'source' => 'vitorsreis/sift',
                'skills' => ['php-review'],
                'agents' => ['codex'],
                'tags' => ['php'],
            ],
        ],
        'meta' => [
            'subcommand' => 'skills find',
            'query' => 'php review',
        ],
    ]);
});

it('returns coding-agent guidance when no query is available in json mode', function (): void {
    $command = new SkillsFindCommand(
        catalog: new SkillCatalog(new SkillsShCatalogClient(
            fetcher: static function (): array {
                throw new RuntimeException('Catalog should not be queried without a search term.');
            },
        )),
    );

    $payload = $command->handle(new CommandRoute('skills.find', globalOptions: ['json' => true]), __DIR__);

    expect($payload)->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'total' => 0,
        ],
        'items' => [],
        'meta' => [
            'subcommand' => 'skills find',
            'query' => '',
            'mode' => 'agent_tip',
        ],
    ]);
});

it('opens the interactive search in a tty even when agent environment variables are present', function (): void {
    $previousCodexShell = getenv('CODEX_SHELL');
    putenv('CODEX_SHELL=1');

    try {
        $command = new SkillsFindCommand(
            catalog: new SkillCatalog(new SkillsShCatalogClient(
                fetcher: static function (): array {
                    throw new RuntimeException('Catalog should not be queried when search is cancelled immediately.');
                },
            )),
            interactivePrompt: new InteractivePrompt(
                keyReader: static fn(): string => 'escape',
                writer: static function (): void {},
            ),
        );

        $payload = $command->handle(new CommandRoute('skills.find'), __DIR__);
    } finally {
        putenv($previousCodexShell === false ? 'CODEX_SHELL' : 'CODEX_SHELL=' . $previousCodexShell);
    }

    expect($payload)->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'total' => 0,
        ],
        'items' => [],
        'meta' => [
            'subcommand' => 'skills find',
            'query' => '',
            'mode' => 'cancelled',
        ],
    ]);
});

it('opens the interactive search for terminal skills find even when stdin is not a tty', function (): void {
    $command = new SkillsFindCommand(
        catalog: new SkillCatalog(new SkillsShCatalogClient(
            fetcher: static function (): array {
                throw new RuntimeException('Catalog should not be queried when search is cancelled immediately.');
            },
        )),
        interactivePrompt: new InteractivePrompt(
            keyReader: static fn(): string => 'escape',
            writer: static function (): void {},
        ),
    );

    $payload = $command->handle(new CommandRoute('skills.find'), __DIR__);

    expect($payload)->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'total' => 0,
        ],
        'items' => [],
        'meta' => [
            'subcommand' => 'skills find',
            'query' => '',
            'mode' => 'cancelled',
        ],
    ]);
});

it('passes no-color to the interactive skills search prompt', function (): void {
    $output = '';
    $command = new SkillsFindCommand(
        catalog: new SkillCatalog(new SkillsShCatalogClient(
            fetcher: static function (): array {
                throw new RuntimeException('Catalog should not be queried when search is cancelled immediately.');
            },
        )),
        interactivePrompt: new InteractivePrompt(
            keyReader: static fn(): string => 'escape',
            writer: static function (string $chunk) use (&$output): void {
                $output .= $chunk;
            },
        ),
    );

    $command->handle(new CommandRoute('skills.find', globalOptions: ['no-color' => true]), __DIR__);

    expect($output)->not->toContain("\033[38;5;");
});

it('passes owner filters to the skill catalog', function (): void {
    /** @var list<string> $urls */
    $urls = [];
    $command = new SkillsFindCommand(new SkillCatalog(new SkillsShCatalogClient(
        fetcher: function (string $url, int $timeout, array $headers) use (&$urls): array {
            $urls[] = $url;

            return [
                'status' => 200,
                'body' => '{"items":[]}',
                'error' => null,
            ];
        },
        baseUrl: 'https://skills.sh/api/search',
    )));

    $command->handle(new CommandRoute('skills.find', ['react'], ['owner' => 'vercel']), __DIR__);

    expect($urls)->toBe(['https://skills.sh/api/search?q=react&limit=10&owner=vercel']);
});
