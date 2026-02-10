<?php

declare(strict_types=1);

namespace Sift\Runtime;

final class ToolLocator
{
    /**
     * @param  list<string>  $candidates
     * @return array{command_prefix: list<string>, path: string}|null
     */
    public function locate(string $cwd, array $candidates): ?array
    {
        foreach ($candidates as $candidate) {
            $path = $this->resolvePath($cwd, $candidate);

            if ($path === null) {
                continue;
            }

            return [
                'command_prefix' => $this->commandPrefix($path, $candidate),
                'path' => $path,
            ];
        }

        return null;
    }

    private function resolvePath(string $cwd, string $candidate): ?string
    {
        if (str_contains($candidate, '/') || str_contains($candidate, '\\')) {
            $resolved = $cwd.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);

            return is_file($resolved) ? $resolved : null;
        }

        return $candidate;
    }

    /**
     * @return list<string>
     */
    private function commandPrefix(string $path, string $candidate): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['bat', 'cmd', 'exe'], true)) {
            return [$path];
        }

        if (is_file($path)) {
            return [PHP_BINARY, $path];
        }

        return [$candidate];
    }
}
