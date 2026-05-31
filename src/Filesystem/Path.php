<?php

declare(strict_types=1);

namespace Sift\Filesystem;

final class Path
{
    public static function normalize(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $trimmed = rtrim($normalized, DIRECTORY_SEPARATOR);

        return $trimmed === '' ? DIRECTORY_SEPARATOR : $trimmed;
    }

    public static function join(string $base, string ...$parts): string
    {
        $segments = [self::normalize($base)];

        foreach ($parts as $part) {
            $segments[] = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part), DIRECTORY_SEPARATOR);
        }

        return self::normalize(implode(DIRECTORY_SEPARATOR, array_filter($segments, static fn(string $part): bool => $part !== '')));
    }

    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    public static function containsDirectorySeparator(string $path): bool
    {
        return str_contains($path, '/') || str_contains($path, '\\');
    }
}
