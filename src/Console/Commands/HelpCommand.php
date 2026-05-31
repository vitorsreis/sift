<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

final class HelpCommand
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'command' => 'help',
                'description' => 'Sift command reference.',
            ],
            'items' => [
                ['type' => 'command', 'name' => 'help'],
                ['type' => 'command', 'name' => 'version'],
                ['type' => 'command', 'name' => 'init'],
                ['type' => 'command', 'name' => 'validate'],
                ['type' => 'command', 'name' => 'tools list'],
                ['type' => 'command', 'name' => 'skills list'],
                ['type' => 'command', 'name' => 'history list'],
                ['type' => 'command', 'name' => 'run <tool>'],
            ],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'help',
            ],
        ];
    }
}
