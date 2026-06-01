<?php

declare(strict_types=1);

namespace Sift\Filesystem;

final class FileLock
{
    private function __construct(private readonly string $path, private mixed $handle) {}

    public function __destruct()
    {
        $this->release();
    }

    public static function acquire(string $path): self
    {
        $handle = @fopen($path, 'c+b');

        if (! is_resource($handle)) {
            throw FilesystemException::writeFailed($path, 'Could not open lock file');
        }

        if (! @flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw FilesystemException::writeFailed($path, 'Could not acquire lock file');
        }

        return new self($path, $handle);
    }

    public function release(): void
    {
        if (! is_resource($this->handle)) {
            return;
        }

        @flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function path(): string
    {
        return $this->path;
    }
}
