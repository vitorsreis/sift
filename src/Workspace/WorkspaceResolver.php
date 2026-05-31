<?php

declare(strict_types=1);

namespace Sift\Workspace;

use Sift\Filesystem\Path;

final readonly class WorkspaceResolver
{
    public function __construct(
        private ?string $homeDirectory = null,
    ) {}

    /**
     * @param array<string, string> $environment
     */
    public function resolve(string $cwd, ?string $configPath = null, array $environment = []): Workspace
    {
        $normalizedCwd = Path::normalize($cwd);
        $globalRoot = $this->globalRoot($environment);

        if ($configPath !== null) {
            $resolvedConfigPath = $this->resolveConfigPath($normalizedCwd, $configPath);

            return new Workspace(
                cwd: $normalizedCwd,
                projectRoot: Path::normalize(dirname($resolvedConfigPath)),
                configPath: $resolvedConfigPath,
                projectDetected: is_file($resolvedConfigPath),
                globalRoot: $globalRoot,
            );
        }

        $siftConfig = $this->findFile($normalizedCwd, 'sift.json');

        if ($siftConfig !== null) {
            return new Workspace(
                cwd: $normalizedCwd,
                projectRoot: Path::normalize(dirname($siftConfig)),
                configPath: $siftConfig,
                projectDetected: true,
                globalRoot: $globalRoot,
            );
        }

        $composerJson = $this->findFile($normalizedCwd, 'composer.json');

        if ($composerJson !== null) {
            return new Workspace(
                cwd: $normalizedCwd,
                projectRoot: Path::normalize(dirname($composerJson)),
                configPath: null,
                projectDetected: true,
                globalRoot: $globalRoot,
            );
        }

        $gitDirectory = $this->findDirectory($normalizedCwd, '.git');

        if ($gitDirectory !== null) {
            return new Workspace(
                cwd: $normalizedCwd,
                projectRoot: Path::normalize(dirname($gitDirectory)),
                configPath: null,
                projectDetected: true,
                globalRoot: $globalRoot,
            );
        }

        return new Workspace(
            cwd: $normalizedCwd,
            projectRoot: $normalizedCwd,
            configPath: null,
            projectDetected: false,
            globalRoot: $globalRoot,
        );
    }

    private function resolveConfigPath(string $cwd, string $configPath): string
    {
        if (Path::isAbsolute($configPath)) {
            return Path::normalize($configPath);
        }

        return Path::join($cwd, $configPath);
    }

    private function findFile(string $cwd, string $file): ?string
    {
        foreach ($this->ancestors($cwd) as $directory) {
            $candidate = Path::join($directory, $file);

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function findDirectory(string $cwd, string $directoryName): ?string
    {
        foreach ($this->ancestors($cwd) as $directory) {
            $candidate = Path::join($directory, $directoryName);

            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function ancestors(string $cwd): array
    {
        $ancestors = [];
        $current = $cwd;

        while (true) {
            $ancestors[] = $current;
            $parent = Path::normalize(dirname($current));

            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        return $ancestors;
    }

    /**
     * @param array<string, string> $environment
     */
    private function globalRoot(array $environment): string
    {
        $siftHome = trim($environment['SIFT_HOME'] ?? '');

        if ($siftHome !== '') {
            return Path::normalize($siftHome);
        }

        if ($this->homeDirectory !== null) {
            return Path::join($this->homeDirectory, '.sift');
        }

        $userProfile = $_SERVER['USERPROFILE'] ?? getenv('USERPROFILE');

        if (is_string($userProfile) && trim($userProfile) !== '') {
            return Path::join($userProfile, '.sift');
        }

        $home = $_SERVER['HOME'] ?? getenv('HOME');

        if (is_string($home) && trim($home) !== '') {
            return Path::join($home, '.sift');
        }

        $cwd = getcwd();

        return Path::join(is_string($cwd) ? $cwd : '.', '.sift');
    }
}
