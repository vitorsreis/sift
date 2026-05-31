<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Sift;

final class VersionCommand implements CommandHandler
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CommandRoute $route, string $cwd): array
    {
        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'version' => Sift::VERSION,
            ],
            'items' => [],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'version',
            ],
        ];
    }
}
