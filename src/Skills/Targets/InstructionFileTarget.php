<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\AtomicWriter;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;
use Sift\Skills\ManagedBlockEditor;
use Sift\Skills\Skill;

final readonly class InstructionFileTarget implements InstructionTarget
{
    public function __construct(
        private string $name,
        private string $relativePath,
        private ManagedBlockEditor $blockEditor = new ManagedBlockEditor(),
        private AtomicWriter $writer = new AtomicWriter(),
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
        $path = $this->targetPath($cwd);
        $current = $this->readCurrentContents($path);
        $body = $this->readSkillContents($skill);
        $next = $this->blockEditor->upsert($current, $skill->name(), $metadata, $body);

        try {
            $this->writer->write($path, $next);
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $path],
            );
        }

        return new SkillTargetInstallResult(
            skillName: $skill->name(),
            target: $this->name,
            path: $path,
            action: str_contains($current, sprintf('<!-- sift:skill:%s:start', $skill->name())) ? 'updated' : 'installed',
        );
    }

    private function targetPath(string $cwd): string
    {
        try {
            return (new PathGuard($cwd))->assertInside(Path::join($cwd, $this->relativePath));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $this->relativePath],
            );
        }
    }

    private function readCurrentContents(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not read target file "%s".', $path),
                context: ['path' => $path],
            );
        }

        return $contents;
    }

    private function readSkillContents(Skill $skill): string
    {
        $contents = file_get_contents($skill->skillFile());

        if (! is_string($contents)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not read skill file "%s".', $skill->skillFile()),
                context: ['path' => $skill->skillFile()],
            );
        }

        return $contents;
    }
}
