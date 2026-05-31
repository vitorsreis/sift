<?php

declare(strict_types=1);

namespace Sift\Filesystem;

use RuntimeException;

final class FilesystemException extends RuntimeException
{
    public static function writeFailed(string $path, string $message): self
    {
        return new self(sprintf('%s: %s', $message, $path));
    }

    public static function readFailed(string $path, string $message): self
    {
        return new self(sprintf('%s: %s', $message, $path));
    }

    public static function pathEscapesRoot(): self
    {
        return new self('Path escapes the allowed root.');
    }
}
