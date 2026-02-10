<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Symfony\Component\Process\Process;

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

        return $this->commandExists($candidate) ? $candidate : null;
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

    private function commandExists(string $command): bool
    {
        $finder = PHP_OS_FAMILY === 'Windows'
            ? ['where', $command]
            : ['which', $command];

        $process = new Process($finder);
        $process->run();

        return $process->isSuccessful();
    }
}
