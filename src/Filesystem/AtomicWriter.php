<?php

declare(strict_types=1);

namespace Sift\Filesystem;

final class AtomicWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw FilesystemException::writeFailed($path, sprintf('Could not create directory "%s"', $directory));
        }

        $temporaryPath = $path . '.tmp.' . bin2hex(random_bytes(8));

        if (file_put_contents($temporaryPath, $contents) === false) {
            throw FilesystemException::writeFailed($temporaryPath, 'Could not write temporary file');
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw FilesystemException::writeFailed($path, 'Could not move temporary file into place');
        }
    }
}
