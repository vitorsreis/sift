<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;

final readonly class HistoryRemoveCommand extends AbstractHistoryCommand
{
    public function handle(CommandRoute $route, string $cwd): array
    {
        $arguments = $route->arguments();

        if ($arguments === []) {
            throw new InvalidUsageException('History remove requires at least one run id.');
        }

        $store = $this->store($route, $cwd);
        $items = [];
        $removed = 0;
        $missing = 0;

        foreach ($arguments as $argument) {
            $runId = $this->runId($argument);
            $status = $store->remove($runId) ? 'removed' : 'missing';

            if ($status === 'removed') {
                ++$removed;
            } else {
                ++$missing;
            }

            $items[] = [
                'run_id' => $runId,
                'status' => $status,
            ];
        }

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'removed' => $removed,
                'missing' => $missing,
            ],
            'items' => $items,
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'history remove',
            ],
        ];
    }
}
