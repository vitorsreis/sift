<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

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

final readonly class CodexSkillTarget implements InstructionTarget
{
    public function __construct(
        private CodexHomeResolver $homeResolver = new CodexHomeResolver(),
        private JsonFile $jsonFile = new JsonFile(),
    ) {}

    public function name(): string
    {
        return 'codex';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function install(string $cwd, Skill $skill, array $metadata): SkillTargetInstallResult
    {
        $targetDirectory = $this->targetDirectory($skill->name());
        $exists = is_dir($targetDirectory);

        $this->copySkillDirectory($skill->path(), $targetDirectory);
        $this->writeMetadata($targetDirectory, $metadata);

        return new SkillTargetInstallResult(
            skillName: $skill->name(),
            target: $this->name(),
            path: Path::join($targetDirectory, 'SKILL.md'),
            action: $exists ? 'updated' : 'installed',
        );
    }

    public function remove(string $cwd, string $skillName): SkillTargetRemoveResult
    {
        $targetDirectory = $this->targetDirectory($skillName);
        $metadata = $this->managedMetadata($targetDirectory, $skillName);

        if (! $metadata instanceof SkillManagedMetadata || ! in_array($this->name(), $metadata->targets(), true)) {
            return new SkillTargetRemoveResult($skillName, $this->name(), $targetDirectory, 'missing');
        }

        $this->deleteTree($targetDirectory);

        return new SkillTargetRemoveResult($skillName, $this->name(), $targetDirectory, 'removed');
    }

    private function targetDirectory(string $skillName): string
    {
        $base = Path::join($this->resolveCodexHome(), 'skills');

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

    private function resolveCodexHome(): string
    {
        return $this->homeResolver->resolve();
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

        $this->ensureDirectory($target);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = Path::join($target, $relative);

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
