<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Skills\SkillCatalog;

final readonly class SkillsFindCommand implements CommandHandler
{
    public function __construct(
        private SkillCatalog $catalog = new SkillCatalog(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $query = trim(implode(' ', $route->arguments()));
        $items = $this->catalog->search($query);

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'total' => count($items),
            ],
            'items' => $items,
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills find',
                'query' => $query,
            ],
        ];
    }
}
