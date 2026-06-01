<?php

declare(strict_types=1);

use Sift\Console\CommandRoute;
use Sift\Console\Commands\SkillsFindCommand;
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
