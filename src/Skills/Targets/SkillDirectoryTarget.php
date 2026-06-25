<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Closure;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\JsonFile;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;
use Sift\Skills\Skill;
use Sift\Skills\SkillManagedMetadata;
use SplFileInfo;

final readonly class SkillDirectoryTarget implements InstructionTarget
{
    public function __construct(
        private string $name,
        private string $projectRelativeDirectory,
        private ?Closure $globalDirectoryResolver = null,
        private bool $global = false,
        private JsonFile $jsonFile = new JsonFile(),
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function install(string $cwd, Skill $skill, array $metadata): SkillTargetInstallResult
    {
        $targetDirectory = $this->targetDirectory($cwd, $skill->name());
        $exists = is_dir($targetDirectory);
        $stagingDirectory = $this->stagingDirectory($targetDirectory);
        $metadata = $this->mergedMetadata($targetDirectory, $skill->name(), $metadata);

        try {
            $this->copySkillDirectory($skill->path(), $stagingDirectory);
            $this->writeMetadata($stagingDirectory, $metadata);
            $this->replaceTarget($stagingDirectory, $targetDirectory);
        } finally {
            $this->deleteTree($stagingDirectory);
        }

        return new SkillTargetInstallResult(
            skillName: $skill->name(),
            target: $this->name,
            path: Path::join($targetDirectory, 'SKILL.md'),
            action: $exists ? 'updated' : 'installed',
        );
    }

    public function remove(string $cwd, string $skillName): SkillTargetRemoveResult
    {
        $targetDirectory = $this->targetDirectory($cwd, $skillName);
        $metadata = $this->managedMetadata($targetDirectory, $skillName);

        if (! $metadata instanceof SkillManagedMetadata || ! in_array($this->name, $metadata->targets(), true)) {
            return new SkillTargetRemoveResult($skillName, $this->name, $targetDirectory, 'missing');
        }

        $remainingTargets = array_values(array_filter(
            $metadata->targets(),
            fn(string $target): bool => $target !== $this->name,
        ));

        if ($remainingTargets !== []) {
            $payload = $metadata->toItem();
            $payload['targets'] = $remainingTargets;
            $this->writeMetadata($targetDirectory, $payload);

            return new SkillTargetRemoveResult($skillName, $this->name, $targetDirectory, 'removed');
        }

        $this->deleteTree($targetDirectory);

        return new SkillTargetRemoveResult($skillName, $this->name, $targetDirectory, 'removed');
    }

    /**
     * @return list<SkillManagedMetadata>
     */
    public function metadata(string $cwd): array
    {
        $skillsDirectory = $this->baseDirectory($cwd);

        if (! is_dir($skillsDirectory)) {
            return [];
        }

        $entries = scandir($skillsDirectory);

        if ($entries === false) {
            return [];
        }

        $items = [];

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $metadata = $this->managedMetadata(Path::join($skillsDirectory, $entry), $entry);

            if ($metadata instanceof SkillManagedMetadata) {
                $items[] = $metadata;
            }
        }

        return $items;
    }

    private function targetDirectory(string $cwd, string $skillName): string
    {
        $base = $this->baseDirectory($cwd);

        try {
            return (new PathGuard($base))->assertInside(Path::join($base, $skillName));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $base],
            );
        }
    }

    private function baseDirectory(string $cwd): string
    {
        if ($this->global) {
            if (! $this->globalDirectoryResolver instanceof Closure) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::UnsupportedTarget,
                    message: sprintf('Skill target "%s" does not support global installs.', $this->name),
                    context: ['target' => $this->name, 'global' => true],
                );
            }

            $directory = ($this->globalDirectoryResolver)();

            if (! is_string($directory)) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::FilesystemError,
                    message: sprintf('Could not resolve global skill directory for target "%s".', $this->name),
                    context: ['target' => $this->name],
                );
            }

            return Path::normalize($directory);
        }

        try {
            return (new PathGuard($cwd))->assertInside(Path::join($cwd, $this->projectRelativeDirectory));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $this->projectRelativeDirectory],
            );
        }
    }

    private function managedMetadata(string $targetDirectory, string $skillName): ?SkillManagedMetadata
    {
        $metadataPath = Path::join($targetDirectory, '.sift-skill.json');

        if (! is_file($metadataPath)) {
            return null;
        }

        try {
            $payload = $this->jsonFile->readObject($metadataPath);
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $metadataPath],
            );
        }

        $metadata = SkillManagedMetadata::fromPayload($payload, $skillName);

        if (! $metadata instanceof SkillManagedMetadata || $metadata->name() !== $skillName) {
            return null;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function mergedMetadata(string $targetDirectory, string $skillName, array $metadata): array
    {
        $existing = $this->managedMetadata($targetDirectory, $skillName);

        if (! $existing instanceof SkillManagedMetadata) {
            return $metadata;
        }

        $targets = $metadata['targets'] ?? [];
        $targets = is_array($targets) ? $targets : [];
        $metadata['targets'] = array_values(array_unique([
            ...$existing->targets(),
            ...array_values(array_filter($targets, static fn(mixed $target): bool => is_string($target) && trim($target) !== '')),
        ]));

        return $metadata;
    }

    private function copySkillDirectory(string $source, string $target): void
    {
        if (! is_dir($source)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Skill source directory "%s" was not found.', $source),
                context: ['path' => $source],
            );
        }

        $this->assertSourceTreeHasNoSymlinks($source);
        $this->ensureDirectory($target);
        $targetGuard = new PathGuard($target);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $this->guardedDestination($targetGuard, Path::join($target, $relative));

            if ($item->isDir()) {
                $this->ensureDirectory($destination);

                continue;
            }

            $this->ensureDirectory(dirname($destination));

            if (! copy($item->getPathname(), $destination)) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::FilesystemError,
                    message: sprintf('Could not copy skill file "%s".', $item->getPathname()),
                    context: ['path' => $item->getPathname()],
                );
            }
        }
    }

    private function assertSourceTreeHasNoSymlinks(string $source): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item instanceof SplFileInfo && $item->isLink()) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::FilesystemError,
                    message: sprintf('Skill source symlink "%s" is not allowed.', $item->getPathname()),
                    context: ['path' => $item->getPathname()],
                );
            }
        }
    }

    private function stagingDirectory(string $targetDirectory): string
    {
        $parent = dirname($targetDirectory);
        $this->ensureDirectory($parent);

        return $this->guardedDestination(
            new PathGuard($parent),
            Path::join($parent, '.sift-' . basename($targetDirectory) . '-' . bin2hex(random_bytes(8))),
        );
    }

    private function replaceTarget(string $stagingDirectory, string $targetDirectory): void
    {
        $this->deleteTree($targetDirectory);

        if (! rename($stagingDirectory, $targetDirectory)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not install skill target "%s".', $targetDirectory),
                context: ['path' => $targetDirectory],
            );
        }
    }

    private function guardedDestination(PathGuard $guard, string $path): string
    {
        try {
            return $guard->assertInside($path);
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $path],
            );
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function writeMetadata(string $targetDirectory, array $metadata): void
    {
        try {
            $this->jsonFile->writeObject(Path::join($targetDirectory, '.sift-skill.json'), $metadata);
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $targetDirectory],
            );
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not create directory "%s".', $path),
                context: ['path' => $path],
            );
        }
    }

    private function deleteTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            $this->deleteFile($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not read directory "%s".', $path),
                context: ['path' => $path],
            );
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $this->deleteTree(Path::join($path, $entry));
        }

        if (! rmdir($path)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not remove directory "%s".', $path),
                context: ['path' => $path],
            );
        }
    }

    private function deleteFile(string $path): void
    {
        if (! unlink($path) && (is_file($path) || is_link($path))) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not remove file "%s".', $path),
                context: ['path' => $path],
            );
        }
    }
}
