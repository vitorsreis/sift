<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
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
        $handles = [];

        try {
            foreach ($this->lockPaths($cwd, $targets) as $path) {
                $handle = @fopen($path, 'c+b');

                if (! is_resource($handle)) {
                    throw $this->filesystemError(sprintf('Could not open skill target lock "%s".', $path), $path);
                }

                if (! @flock($handle, LOCK_EX | LOCK_NB)) {
                    fclose($handle);

                    throw $this->filesystemError(sprintf('Could not acquire skill target lock "%s".', $path), $path);
                }

                $handles[] = $handle;
            }

            return $operation();
        } finally {
            foreach (array_reverse($handles) as $handle) {
                @flock($handle, LOCK_UN);
                fclose($handle);
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
            if (preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/', $target) !== 1) {
                throw $this->filesystemError(sprintf('Invalid skill target lock name "%s".', $target), $directory);
            }

            $paths[] = $guard->assertInside(Path::join($directory, $target . '.lock'));
        }

        return $paths;
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
