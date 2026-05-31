<?php

declare(strict_types=1);

namespace Sift\Filesystem;

use InvalidArgumentException;

final readonly class TempFileFactory
{
    public function __construct(
        private string $directory,
    ) {
        if (trim($directory) === '') {
            throw new InvalidArgumentException('Temporary directory cannot be empty.');
        }
    }

    public function create(string $prefix = 'sift-', string $suffix = '.tmp'): TempFile
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0777, true) && ! is_dir($this->directory)) {
            throw FilesystemException::writeFailed($this->directory, 'Could not create temporary directory');
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8)) . $suffix;

        if (file_put_contents($path, '') === false) {
            throw FilesystemException::writeFailed($path, 'Could not create temporary file');
        }

        return new TempFile($path);
    }
}
