<?php

declare(strict_types=1);

namespace Sift\Runtime;

final class PathHelper
{
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
