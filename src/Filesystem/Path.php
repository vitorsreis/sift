<?php

declare(strict_types=1);

namespace Sift\Filesystem;

final class Path
{
    public static function normalize(string $path): string
    {
        $streamWrapper = self::streamWrapper($path);

        if ($streamWrapper !== null) {
            [$scheme, $streamPath] = $streamWrapper;
            $normalized = str_replace('\\', '/', $streamPath);
            $trimmed = rtrim($normalized, '/');

            return $scheme . ($trimmed === '' ? '/' : $trimmed);
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $trimmed = rtrim($normalized, DIRECTORY_SEPARATOR);

        return $trimmed === '' ? DIRECTORY_SEPARATOR : $trimmed;
    }

    public static function join(string $base, string ...$parts): string
    {
        if (self::streamWrapper($base) !== null) {
            $segments = [self::normalize($base)];

            foreach ($parts as $part) {
                $segments[] = trim(str_replace('\\', '/', $part), '/');
            }

            return self::normalize(implode('/', array_filter($segments, static fn(string $part): bool => $part !== '')));
        }

        $segments = [self::normalize($base)];

        foreach ($parts as $part) {
            $segments[] = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part), DIRECTORY_SEPARATOR);
        }

        return self::normalize(implode(DIRECTORY_SEPARATOR, array_filter($segments, static fn(string $part): bool => $part !== '')));
    }

    public static function isAbsolute(string $path): bool
    {
        if (self::streamWrapper($path) !== null) {
            return true;
        }

        if (str_starts_with($path, '/')) {
            return true;
        }

        if (str_starts_with($path, '\\\\')) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    public static function containsDirectorySeparator(string $path): bool
    {
        return str_contains($path, '/') || str_contains($path, '\\');
    }

    /**
     * @return null|array{0: string, 1: string}
     */
    private static function streamWrapper(string $path): ?array
    {
        if (preg_match('#^(?P<scheme>[A-Za-z][A-Za-z0-9+.-]*://)(?P<path>.*)$#', $path, $matches) !== 1) {
            return null;
        }

        return [$matches['scheme'], $matches['path']];
    }
}
