<?php

declare(strict_types=1);

namespace Sift\Filesystem;

use InvalidArgumentException;

final readonly class TempFile
{
    public function __construct(
        private string $path,
    ) {
        if (trim($path) === '') {
            throw new InvalidArgumentException('Temporary file path cannot be empty.');
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function remove(): void
    {
        if ($this->exists()) {
            @unlink($this->path);
        }
    }
}
