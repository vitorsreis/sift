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
        $targetDirectory = $this->targetDirectory($skill);
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

    private function targetDirectory(Skill $skill): string
    {
        $base = Path::join($this->resolveCodexHome(), 'skills');

        try {
            return (new PathGuard($base))->assertInside(Path::join($base, $skill->name()));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $base],
            );
        }
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
}
