<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Exceptions\UserFacingException;

final class ViewService
{
    public function __construct(
        private readonly FileRunStore $runStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function list(string $cwd, int $limit, int $offset): array
    {
        $listing = $this->runStore->list($cwd, $limit, $offset);

        return [
            'status' => 'ok',
            'scope' => 'runs',
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($listing['items']),
            'total' => $listing['total'],
            'items' => $listing['items'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clear(string $cwd): array
    {
        $cleared = $this->runStore->clear($cwd);

        return [
            'status' => 'cleared',
            'deleted' => $cleared['deleted'],
            'path' => $cleared['path'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function view(string $cwd, string $runId, string $scope, int $limit, int $offset): array
    {
        $stored = $this->runStore->get($cwd, $runId);

        if ($stored === null) {
            throw UserFacingException::runNotFound($runId);
        }

        $result = is_array($stored['result'] ?? null) ? $stored['result'] : [];

        return match ($scope) {
            'summary' => [
                'status' => (string) ($result['status'] ?? 'unknown'),
                'summary' => is_array($result['summary'] ?? null) ? $result['summary'] : [],
                'run_id' => $runId,
            ],
            'meta' => [
                'status' => (string) ($result['status'] ?? 'unknown'),
                'meta' => is_array($result['meta'] ?? null) ? $result['meta'] : [],
                'run_id' => $runId,
            ],
            'artifacts' => $this->sliceScope($result, 'artifacts', $runId, $limit, $offset),
            'extra' => [
                'status' => (string) ($result['status'] ?? 'unknown'),
                'extra' => is_array($result['extra'] ?? null) ? $result['extra'] : [],
                'run_id' => $runId,
            ],
            'items' => $this->sliceScope($result, 'items', $runId, $limit, $offset),
            default => [
                ...$result,
                'run_id' => $runId,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function sliceScope(array $result, string $scope, string $runId, int $limit, int $offset): array
    {
        $items = is_array($result[$scope] ?? null) ? array_values($result[$scope]) : [];
        $slice = array_slice($items, $offset, $limit);

        return [
            'status' => (string) ($result['status'] ?? 'unknown'),
            'scope' => $scope,
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($slice),
            'total' => count($items),
            'items' => $slice,
            'run_id' => $runId,
        ];
    }
}
