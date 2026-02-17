<?php

declare(strict_types=1);

namespace Sift\Runtime;

use JsonException;
use RuntimeException;
use Sift\Core\NormalizedResult;

final class FileRunStore
{
    /**
     * @param  array{enabled?: bool, max_files?: int, path?: string}  $historyConfig
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(string $cwd, int $limit, int $offset, array $historyConfig = []): array
    {
        $directory = $this->directory($cwd, $historyConfig);

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
                '_stored_at' => (int) ($stored['stored_at'] ?? $stored['created_at'] ?? 0),
                'tool' => (string) ($result['tool'] ?? 'unknown'),
                'status' => (string) ($result['status'] ?? 'unknown'),
                ...$summary,
            ];
        }

        usort($items, static fn (array $left, array $right): int => [$right['_stored_at'], $right['created_at'], $right['run_id']] <=> [$left['_stored_at'], $left['created_at'], $left['run_id']]);

        $items = array_map(static function (array $item): array {
            unset($item['_stored_at']);

            return $item;
        }, $items);

        return [
            'items' => array_slice($items, $offset, $limit),
            'total' => count($items),
        ];
    }

    /**
     * @param  array{enabled?: bool, max_files?: int, path?: string}  $historyConfig
     */
    public function put(string $cwd, NormalizedResult $result, array $historyConfig = []): string
    {
        $directory = $this->directory($cwd, $historyConfig);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create history directory: %s', $directory));
        }

        $runId = bin2hex(random_bytes(4));
        $createdAt = strtotime((string) ($result->meta['created_at'] ?? ''));
        $payload = [
            'created_at' => $createdAt === false ? time() : $createdAt,
            'stored_at' => (int) round(microtime(true) * 1000000),
            'result' => $result->toArray(),
        ];

        file_put_contents(
            $directory.'/'.$runId.'.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        $this->rotate($directory, $this->maxFiles($historyConfig));

        return $runId;
    }

    /**
     * @param  array{enabled?: bool, max_files?: int, path?: string}  $historyConfig
     * @return array<string, mixed>|null
     */
    public function get(string $cwd, string $runId, array $historyConfig = []): ?array
    {
        $path = $this->directory($cwd, $historyConfig).'/'.$runId.'.json';

        if (! is_file($path)) {
            return null;
        }

        return $this->read($path);
    }

    /**
     * @param  array{enabled?: bool, max_files?: int, path?: string}  $historyConfig
     * @return array{deleted: int, path: string}
     */
    public function clear(string $cwd, array $historyConfig = []): array
    {
        $directory = $this->directory($cwd, $historyConfig);

        if (! is_dir($directory)) {
            return [
                'deleted' => 0,
                'path' => $directory,
            ];
        }

        $paths = glob($directory.'/*.json');
        $deleted = 0;

        if (is_array($paths)) {
            foreach ($paths as $path) {
                if (is_file($path) && @unlink($path)) {
                    $deleted++;
                }
            }
        }

        $remaining = glob($directory.'/*');

        if ($remaining === false || $remaining === []) {
            @rmdir($directory);
            $parent = dirname($directory);
            $parentRemaining = glob($parent.'/*');

            if ($parentRemaining === false || $parentRemaining === []) {
                @rmdir($parent);
            }
        }

        return [
            'deleted' => $deleted,
            'path' => $directory,
        ];
    }

    /**
     * @param  array{enabled?: bool, max_files?: int, path?: string}  $historyConfig
     */
    private function directory(string $cwd, array $historyConfig = []): string
    {
        $path = is_string($historyConfig['path'] ?? null) && trim((string) $historyConfig['path']) !== ''
            ? trim((string) $historyConfig['path'])
            : '.sift/history';

        if (PathHelper::isAbsolute($path)) {
            return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }

        return $cwd.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @param  array{enabled?: bool, max_files?: int, path?: string}  $historyConfig
     */
    private function maxFiles(array $historyConfig): int
    {
        $maxFiles = $historyConfig['max_files'] ?? 50;

        return is_int($maxFiles) && $maxFiles > 0 ? $maxFiles : 50;
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

    private function rotate(string $directory, int $maxFiles): void
    {
        $paths = glob($directory.'/*.json');

        if (! is_array($paths) || count($paths) <= $maxFiles) {
            return;
        }

        $items = [];

        foreach ($paths as $path) {
            $stored = $this->read($path);
            $runId = pathinfo($path, PATHINFO_FILENAME);
            $items[] = [
                'path' => $path,
                'run_id' => $runId,
                'created_at' => (int) ($stored['created_at'] ?? filemtime($path) ?: 0),
                'stored_at' => (int) ($stored['stored_at'] ?? filemtime($path) ?: 0),
            ];
        }

        usort($items, static fn (array $left, array $right): int => [$left['stored_at'], $left['created_at'], $left['run_id']] <=> [$right['stored_at'], $right['created_at'], $right['run_id']]);

        foreach (array_slice($items, 0, max(0, count($items) - $maxFiles)) as $item) {
            @unlink((string) $item['path']);
        }
    }
}
