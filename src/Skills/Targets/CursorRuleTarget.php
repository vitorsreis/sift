<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use JsonException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\AtomicWriter;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;
use Sift\Skills\ManagedBlockEditor;
use Sift\Skills\Skill;
use Sift\Skills\SkillManagedMetadata;

final readonly class CursorRuleTarget implements InstructionTarget
{
    public function __construct(
        private ManagedBlockEditor $blockEditor = new ManagedBlockEditor(),
        private AtomicWriter $writer = new AtomicWriter(),
    ) {}

    public function name(): string
    {
        return 'cursor';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function install(string $cwd, Skill $skill, array $metadata): SkillTargetInstallResult
    {
        $path = $this->targetPath($cwd, $skill->name());
        $current = is_file($path) ? $this->readFile($path) : '';
        $rule = $this->renderRule($skill, $metadata);

        try {
            $this->writer->write($path, $rule);
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $path],
            );
        }

        return new SkillTargetInstallResult(
            skillName: $skill->name(),
            target: $this->name(),
            path: $path,
            action: str_contains($current, sprintf('<!-- sift:skill:%s:start', $skill->name())) ? 'updated' : 'installed',
        );
    }

    public function remove(string $cwd, string $skillName): SkillTargetRemoveResult
    {
        $path = $this->targetPath($cwd, $skillName);

        if (! is_file($path)) {
            return new SkillTargetRemoveResult($skillName, $this->name(), $path, 'missing');
        }

        $metadata = array_filter(
            $this->blockEditor->metadata($this->readFile($path)),
            fn(SkillManagedMetadata $metadata): bool => $metadata->name() === $skillName
                && in_array($this->name(), $metadata->targets(), true),
        );

        if ($metadata === []) {
            return new SkillTargetRemoveResult($skillName, $this->name(), $path, 'missing');
        }

        if (! unlink($path) && is_file($path)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not remove cursor rule "%s".', $path),
                context: ['path' => $path],
            );
        }

        return new SkillTargetRemoveResult($skillName, $this->name(), $path, 'removed');
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function renderRule(Skill $skill, array $metadata): string
    {
        return implode(PHP_EOL, [
            '---',
            'description: ' . $this->yamlString($skill->description()),
            'alwaysApply: false',
            '---',
            '',
            rtrim($this->blockEditor->upsert('', $skill->name(), $metadata, $this->readFile($skill->skillFile()))),
            '',
        ]);
    }

    private function yamlString(string $value): string
    {
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $jsonException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $jsonException->getMessage(),
            );
        }

        return $encoded;
    }

    private function targetPath(string $cwd, string $skillName): string
    {
        try {
            return (new PathGuard($cwd))->assertInside(Path::join($cwd, '.cursor/rules', $skillName . '.mdc'));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => '.cursor/rules/' . $skillName . '.mdc'],
            );
        }
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not read file "%s".', $path),
                context: ['path' => $path],
            );
        }

        return $contents;
    }
}
