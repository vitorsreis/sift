<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;

final readonly class HistoryListCommand extends AbstractHistoryCommand
{
    public function handle(CommandRoute $route, string $cwd): array
    {
        $limit = $this->intOption($route, 'limit', 20);
        $offset = $this->intOption($route, 'offset', 0);

        if ($limit < 1) {
            throw new InvalidUsageException('History list limit must be greater than zero.');
        }

        if ($offset < 0) {
            throw new InvalidUsageException('History list offset must be zero or greater.');
        }

        $runs = $this->store($route, $cwd)->list();

        usort(
            $runs,
            static fn(array $left, array $right): int => strcmp(
                is_string($right['run_id'] ?? null) ? $right['run_id'] : '',
                is_string($left['run_id'] ?? null) ? $left['run_id'] : '',
            ),
        );

        $items = array_map(
            $this->compactRun(...),
            array_slice($runs, $offset, $limit),
        );

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'total' => count($runs),
                'returned' => count($items),
                'limit' => $limit,
                'offset' => $offset,
            ],
            'items' => $items,
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'history list',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $run
     *
     * @return array<string, mixed>
     */
    private function compactRun(array $run): array
    {
        if (($run['type'] ?? null) === 'error') {
            return [
                'type' => 'error',
                'run_id' => $this->stringValue($run['run_id'] ?? null),
                'status' => 'error',
                'message' => $this->stringValue($run['message'] ?? null),
                'error' => $this->stringValue($run['error'] ?? null),
            ];
        }

        return [
            'run_id' => $this->stringValue($run['run_id'] ?? null),
            'created_at' => $this->createdAt($run),
            'tool' => $this->stringValue($run['tool'] ?? null),
            'status' => $this->stringValue($run['status'] ?? null),
            'summary' => $this->objectValue($run['summary'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $run
     */
    private function createdAt(array $run): string
    {
        $meta = $this->objectValue($run['meta'] ?? []);
        $createdAt = $meta['created_at'] ?? null;

        return $this->stringValue($createdAt);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
