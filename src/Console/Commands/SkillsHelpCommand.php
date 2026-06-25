<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;

final class SkillsHelpCommand implements CommandHandler
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CommandRoute $route, string $cwd): array
    {
        unset($route, $cwd);

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'command' => 'skills',
                'description' => 'Manage Sift agent skills.',
            ],
            'items' => [
                ['type' => 'command', 'name' => 'skills add'],
                ['type' => 'command', 'name' => 'skills find'],
                ['type' => 'command', 'name' => 'skills list'],
                ['type' => 'command', 'name' => 'skills remove'],
                ['type' => 'command', 'name' => 'skills update'],
                ['type' => 'command', 'name' => 'skills init'],
            ],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills',
            ],
        ];
    }
}
