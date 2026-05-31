<?php

declare(strict_types=1);

namespace Sift\Execution;

use InvalidArgumentException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

final class ToolLocator
{
    /**
     * @var array<string, LocatedTool>
     */
    private array $cache = [];

    /**
     * @param list<string>|null $pathExtensions
     */
    public function __construct(
        private readonly ?string $pathEnvironment = null,
        private readonly string $pathSeparator = PATH_SEPARATOR,
        private readonly ?array $pathExtensions = null,
    ) {
        if ($pathSeparator === '') {
            throw new InvalidArgumentException('Path separator cannot be empty.');
        }
    }

    public function locate(string $tool, string $candidate, string $cwd): LocatedTool
    {
        $cacheKey = implode("\0", [$tool, $candidate, $cwd, $this->pathEnvironment()]);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $located = $this->locateUncached($tool, $candidate, $cwd);
        $this->cache[$cacheKey] = $located;

        return $located;
    }

    private function locateUncached(string $tool, string $candidate, string $cwd): LocatedTool
    {
        if ($this->isPathLike($candidate)) {
            $source = $this->isAbsolutePath($candidate) ? 'absolute' : 'relative';
            $binary = $source === 'absolute' ? $candidate : $cwd . DIRECTORY_SEPARATOR . $candidate;
            $realPath = realpath($binary);

            if ($realPath !== false && is_file($realPath)) {
                return new LocatedTool($tool, $realPath, $candidate, $source);
            }

            throw $this->notFound($tool, $candidate);
        }

        foreach ($this->pathDirectories() as $directory) {
            foreach ($this->pathCandidates($candidate) as $pathCandidate) {
                $realPath = realpath($directory . DIRECTORY_SEPARATOR . $pathCandidate);

                if ($realPath !== false && is_file($realPath)) {
                    return new LocatedTool($tool, $realPath, $candidate, 'path');
                }
            }
        }

        throw $this->notFound($tool, $candidate);
    }

    /**
     * @return list<string>
     */
    private function pathDirectories(): array
    {
        $directories = [];

        foreach (explode($this->pathSeparator(), $this->pathEnvironment()) as $directory) {
            $directory = trim($directory);

            if ($directory !== '') {
                $directories[] = $directory;
            }
        }

        return $directories;
    }

    /**
     * @return non-empty-string
     */
    private function pathSeparator(): string
    {
        if ($this->pathSeparator === '') {
            throw new InvalidArgumentException('Path separator cannot be empty.');
        }

        return $this->pathSeparator;
    }

    /**
     * @return non-empty-list<string>
     */
    private function pathCandidates(string $candidate): array
    {
        $candidates = [$candidate];

        if (pathinfo($candidate, PATHINFO_EXTENSION) !== '') {
            return $candidates;
        }

        foreach ($this->extensions() as $extension) {
            $candidates[] = $candidate . $extension;
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private function extensions(): array
    {
        if (is_array($this->pathExtensions)) {
            return array_values(array_filter(
                array_map($this->normalizeExtension(...), $this->pathExtensions),
                static fn(string $extension): bool => $extension !== '',
            ));
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $pathExt = getenv('PATHEXT');

        if (! is_string($pathExt) || trim($pathExt) === '') {
            $pathExt = '.COM;.EXE;.BAT;.CMD';
        }

        return array_values(array_filter(
            array_map($this->normalizeExtension(...), explode(';', $pathExt)),
            static fn(string $extension): bool => $extension !== '',
        ));
    }

    private function normalizeExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));

        if ($extension === '') {
            return '';
        }

        return str_starts_with($extension, '.') ? $extension : '.' . $extension;
    }

    private function pathEnvironment(): string
    {
        $path = $this->pathEnvironment ?? getenv('PATH');

        return is_string($path) ? $path : '';
    }

    private function isPathLike(string $candidate): bool
    {
        if ($this->isAbsolutePath($candidate)) {
            return true;
        }

        if (str_contains($candidate, '/')) {
            return true;
        }

        return str_contains($candidate, '\\');
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}[^\\\\\/]+[\\\\\/][^\\\\\/]+|[\\\\\/])/', $path) === 1;
    }

    private function notFound(string $tool, string $candidate): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ToolNotFound,
            message: sprintf('Tool "%s" could not be located.', $tool),
            hint: 'Install the tool or configure its binary path in sift.json.',
            context: [
                'tool' => $tool,
                'candidate' => $candidate,
            ],
        );
    }
}
