<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;

final readonly class HistoryClearCommand extends AbstractHistoryCommand
{
    public function handle(CommandRoute $route, string $cwd): array
    {
        if ($route->arguments() !== []) {
            throw new InvalidUsageException('History clear does not accept run ids.');
        }

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'removed' => $this->store($route, $cwd)->clearAll(),
            ],
            'items' => [],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'history clear',
            ],
        ];
    }
}
