<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\FileLock;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;

final readonly class SkillTargetLock
{
    /**
     * @template T
     *
     * @param list<string> $targets
     * @param callable(): T $operation
     *
     * @return T
     */
    public function synchronized(string $cwd, array $targets, callable $operation): mixed
    {
        $locks = [];

        try {
            foreach ($this->lockPaths($cwd, $targets) as $path) {
                try {
                    $locks[] = FileLock::acquire($path);
                } catch (FilesystemException $filesystemException) {
                    throw $this->filesystemError($filesystemException->getMessage(), $path);
                }
            }

            return $operation();
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * @param list<string> $targets
     *
     * @return list<string>
     */
    private function lockPaths(string $cwd, array $targets): array
    {
        $guard = new PathGuard($cwd);
        $directory = $this->lockDirectory($guard, $cwd);
        $normalizedTargets = array_values(array_unique($targets));
        sort($normalizedTargets);
        $paths = [];

        foreach ($normalizedTargets as $target) {
            $paths[] = $guard->assertInside(Path::join($directory, $this->lockName($target) . '.lock'));
        }

        return $paths;
    }

    private function lockName(string $target): string
    {
        $normalized = strtolower(trim($target));
        $safe = preg_replace('/[^a-z0-9_.-]+/', '-', $normalized) ?? '';
        $safe = trim($safe, '-_.');

        if ($safe === '') {
            throw $this->filesystemError(sprintf('Invalid skill target lock name "%s".', $target), '.');
        }

        if ($safe === $normalized) {
            return $safe;
        }

        return substr($safe, 0, 54) . '-' . substr(sha1($normalized), 0, 8);
    }

    private function lockDirectory(PathGuard $guard, string $cwd): string
    {
        try {
            $directory = $guard->assertInside(Path::join($cwd, '.sift/locks/skills'));
        } catch (FilesystemException $filesystemException) {
            throw $this->filesystemError($filesystemException->getMessage(), $cwd);
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw $this->filesystemError(sprintf('Could not create skill target lock directory "%s".', $directory), $directory);
        }

        return $directory;
    }

    private function filesystemError(string $message, string $path): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::FilesystemError,
            message: $message,
            context: ['path' => $path],
        );
    }
}
