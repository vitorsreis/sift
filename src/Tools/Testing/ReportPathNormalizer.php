<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

final readonly class ReportPathNormalizer
{
    public function normalize(string $path, string $cwd): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $separator = strpos($normalized, '::');

        if ($separator !== false) {
            $normalized = substr($normalized, 0, $separator);
        }

        $cwd = rtrim(str_replace('\\', '/', $cwd), '/');
        $lowerPath = strtolower($normalized);
        $lowerCwd = strtolower($cwd);

        if ($cwd !== '' && $lowerPath === $lowerCwd) {
            return '';
        }

        if ($cwd !== '' && str_starts_with($lowerPath, $lowerCwd . '/')) {
            return ltrim(substr($normalized, strlen($cwd)), '/');
        }

        return $normalized;
    }
}
