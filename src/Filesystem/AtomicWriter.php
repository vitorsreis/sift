<?php

declare(strict_types=1);

namespace Sift\Filesystem;

final class AtomicWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        $existingMode = $this->fileMode($path);

        $this->ensureDirectory($directory, $path);

        $temporaryPath = $path . '.tmp.' . bin2hex(random_bytes(8));

        if (file_put_contents($temporaryPath, $contents) === false) {
            throw FilesystemException::writeFailed($temporaryPath, 'Could not write temporary file');
        }

        if (! chmod($temporaryPath, $existingMode ?? 0644)) {
            @unlink($temporaryPath);

            throw FilesystemException::writeFailed($temporaryPath, 'Could not set temporary file permissions');
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw FilesystemException::writeFailed($path, 'Could not move temporary file into place');
        }
    }

    private function ensureDirectory(string $directory, string $path): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw FilesystemException::writeFailed($path, sprintf('Could not create directory "%s"', $directory));
        }

        if (! chmod($directory, 0755)) {
            throw FilesystemException::writeFailed($path, sprintf('Could not set directory permissions for "%s"', $directory));
        }
    }

    private function fileMode(string $path): ?int
    {
        if (! is_file($path)) {
            return null;
        }

        $permissions = fileperms($path);

        return is_int($permissions) ? $permissions & 0777 : null;
    }
}
