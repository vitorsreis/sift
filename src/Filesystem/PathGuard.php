<?php

declare(strict_types=1);

namespace Sift\Filesystem;

final readonly class PathGuard
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = $this->canonicalize($root);
    }

    public function assertInside(string $path): string
    {
        $canonicalPath = $this->canonicalize($path);

        if ($this->isInsideRoot($canonicalPath)) {
            return $canonicalPath;
        }

        throw FilesystemException::pathEscapesRoot();
    }

    private function canonicalize(string $path): string
    {
        $normalized = Path::normalize($path);
        $realPath = realpath($normalized);

        if (is_string($realPath)) {
            return Path::normalize($realPath);
        }

        $missingParts = [];
        $current = $normalized;

        while (! file_exists($current)) {
            $parent = dirname($current);

            if ($parent === $current) {
                break;
            }

            array_unshift($missingParts, basename($current));
            $current = $parent;
        }

        $realParent = realpath($current);
        $base = is_string($realParent) ? Path::normalize($realParent) : Path::normalize($current);

        return $missingParts === [] ? $base : Path::join($base, ...$missingParts);
    }

    private function isInsideRoot(string $path): bool
    {
        $root = $this->comparisonValue($this->root);
        $candidate = $this->comparisonValue($path);

        return $candidate === $root || str_starts_with($candidate, $root . DIRECTORY_SEPARATOR);
    }

    private function comparisonValue(string $path): string
    {
        $normalized = Path::normalize($path);

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }
}
