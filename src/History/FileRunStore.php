<?php

declare(strict_types=1);

namespace Sift\History;

use InvalidArgumentException;
use Sift\Core\ItemType;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\JsonFile;

final readonly class FileRunStore implements RunStore
{
    public function __construct(
        private string $historyPath,
        private JsonFile $jsonFile = new JsonFile(),
    ) {}

    public function store(array $document): void
    {
        $runId = $this->runId($document['run_id'] ?? null);
        $this->ensureDirectory($this->runsPath());
        $path = $this->runPath($runId);

        $this->jsonFile->writeObject($path, $document);
        $this->restrictFile($path);
    }

    public function read(string $runId): ?array
    {
        $runId = $this->runId($runId);
        $path = $this->runPath($runId);

        if (! is_file($path)) {
            return null;
        }

        return $this->jsonFile->readObject($path);
    }

    public function list(): array
    {
        $runsPath = $this->runsPath();

        if (! is_dir($runsPath)) {
            return [];
        }

        $files = glob($runsPath . DIRECTORY_SEPARATOR . '*.json');

        if ($files === false) {
            return [];
        }

        sort($files);
        $runs = [];

        foreach ($files as $file) {
            $runId = basename($file, '.json');

            try {
                $runs[] = $this->jsonFile->readObject($file);
            } catch (FilesystemException $filesystemException) {
                $runs[] = [
                    'run_id' => $runId,
                    'type' => ItemType::Error->value,
                    'tool' => 'history',
                    'status' => 'error',
                    'summary' => [],
                    'message' => 'Could not read history run.',
                    'error' => $filesystemException->getMessage(),
                ];
            }
        }

        return $runs;
    }

    public function remove(string $runId): bool
    {
        $runId = $this->runId($runId);
        $path = $this->runPath($runId);

        if (! is_file($path)) {
            return false;
        }

        return unlink($path);
    }

    public function clearAll(): int
    {
        $path = $this->normalizedHistoryPath();

        if (! is_dir($path)) {
            return 0;
        }

        return $this->removeTree($path);
    }

    private function runsPath(): string
    {
        return $this->normalizedHistoryPath() . DIRECTORY_SEPARATOR . 'runs';
    }

    private function runPath(string $runId): string
    {
        return $this->runsPath() . DIRECTORY_SEPARATOR . $runId . '.json';
    }

    private function normalizedHistoryPath(): string
    {
        return rtrim($this->historyPath, "\\/");
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path)) {
            throw FilesystemException::writeFailed($path, 'Could not create history directory');
        }

        $this->restrictDirectory($path);
        $parent = dirname($path);

        if ($parent !== $path && is_dir($parent)) {
            $this->restrictDirectory($parent);
        }
    }

    private function restrictDirectory(string $path): void
    {
        @chmod($path, 0700);
    }

    private function restrictFile(string $path): void
    {
        @chmod($path, 0600);
    }

    private function runId(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^run_[a-f0-9]{32}$/', $value) !== 1) {
            throw new InvalidArgumentException('History run id must match run_[a-f0-9]{32}.');
        }

        return $value;
    }

    private function removeTree(string $path): int
    {
        $removedFiles = 0;
        $entries = scandir($path);

        if ($entries === false) {
            throw FilesystemException::writeFailed($path, 'Could not scan history directory');
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($child) && ! is_link($child)) {
                $removedFiles += $this->removeTree($child);
                continue;
            }

            if (! unlink($child)) {
                throw FilesystemException::writeFailed($child, 'Could not remove history file');
            }

            ++$removedFiles;
        }

        if (! rmdir($path)) {
            throw FilesystemException::writeFailed($path, 'Could not remove history directory');
        }

        return $removedFiles;
    }
}
