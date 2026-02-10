<?php

declare(strict_types=1);

namespace Sift\Runtime;

use JsonException;
use RuntimeException;
use Sift\Core\NormalizedResult;

final class FileRunStore
{
    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(string $cwd, int $limit, int $offset): array
    {
        $directory = $this->directory($cwd);

        if (! is_dir($directory)) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        $paths = glob($directory.'/*.json');

        if (! is_array($paths) || $paths === []) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        $items = [];

        foreach ($paths as $path) {
            $stored = $this->read($path);

            if ($stored === null) {
                continue;
            }

            $result = is_array($stored['result'] ?? null) ? $stored['result'] : [];
            $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];

            $items[] = [
                'run_id' => pathinfo($path, PATHINFO_FILENAME),
                'created_at' => (int) ($stored['created_at'] ?? 0),
                'tool' => (string) ($result['tool'] ?? 'unknown'),
                'status' => (string) ($result['status'] ?? 'unknown'),
                ...$summary,
            ];
        }

        usort($items, static fn (array $left, array $right): int => [$right['created_at'], $right['run_id']] <=> [$left['created_at'], $left['run_id']]);

        return [
            'items' => array_slice($items, $offset, $limit),
            'total' => count($items),
        ];
    }

    public function put(string $cwd, NormalizedResult $result): string
    {
        $directory = $this->directory($cwd);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create history directory: %s', $directory));
        }

        $runId = bin2hex(random_bytes(4));
        $payload = [
            'created_at' => time(),
            'result' => $result->toArray(),
        ];

        file_put_contents(
            $directory.'/'.$runId.'.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        return $runId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $cwd, string $runId): ?array
    {
        $path = $this->directory($cwd).'/'.$runId.'.json';

        if (! is_file($path)) {
            return null;
        }

        return $this->read($path);
    }

    private function directory(string $cwd): string
    {
        return $cwd.'/.sift/history';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(string $path): ?array
    {
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
