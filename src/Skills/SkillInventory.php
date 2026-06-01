<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\JsonFile;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;
use Sift\Skills\Targets\CodexHomeResolver;
use Sift\Skills\Targets\InstructionTargetRegistry;

final readonly class SkillInventory
{
    public function __construct(
        private ManagedBlockEditor $blockEditor = new ManagedBlockEditor(),
        private InstructionTargetRegistry $targetRegistry = new InstructionTargetRegistry(),
        private CodexHomeResolver $codexHomeResolver = new CodexHomeResolver(),
        private JsonFile $jsonFile = new JsonFile(),
    ) {}

    /**
     * @param list<string> $targets
     *
     * @return list<SkillManagedMetadata>
     */
    public function list(string $cwd, array $targets): array
    {
        $items = [];

        foreach ($targets as $target) {
            $resolvedTarget = $this->targetRegistry->resolve($target);
            $targetName = $resolvedTarget->name();

            if ($targetName === 'codex') {
                foreach ($this->codexMetadata() as $metadata) {
                    if (in_array($targetName, $metadata->targets(), true)) {
                        $items[$metadata->name()] = $metadata;
                    }
                }

                continue;
            }

            if ($targetName === 'cursor') {
                foreach ($this->cursorMetadata($cwd) as $metadata) {
                    if (in_array($targetName, $metadata->targets(), true)) {
                        $items[$metadata->name()] = $metadata;
                    }
                }

                continue;
            }

            if ($targetName === 'generic') {
                foreach ($this->instructionFileMetadata($cwd, 'AGENTS.md') as $metadata) {
                    if (in_array($targetName, $metadata->targets(), true)) {
                        $items[$metadata->name()] = $metadata;
                    }
                }

                continue;
            }

            foreach ($this->instructionFileMetadata($cwd, $this->instructionFilePath($targetName)) as $metadata) {
                if (in_array($targetName, $metadata->targets(), true)) {
                    $items[$metadata->name()] = $metadata;
                }
            }
        }

        ksort($items);

        return array_values($items);
    }

    /**
     * @return list<SkillManagedMetadata>
     */
    private function codexMetadata(): array
    {
        $skillsDirectory = Path::join($this->codexHomeResolver->resolve(), 'skills');

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

            $metadataPath = Path::join($skillsDirectory, $entry, '.sift-skill.json');

            if (! is_file($metadataPath)) {
                continue;
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

            $metadata = SkillManagedMetadata::fromPayload($payload, $entry);

            if ($metadata instanceof SkillManagedMetadata) {
                $items[] = $metadata;
            }
        }

        return $items;
    }

    /**
     * @return list<SkillManagedMetadata>
     */
    private function cursorMetadata(string $cwd): array
    {
        try {
            $rulesDirectory = (new PathGuard($cwd))->assertInside(Path::join($cwd, '.cursor/rules'));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => '.cursor/rules'],
            );
        }

        if (! is_dir($rulesDirectory)) {
            return [];
        }

        $entries = scandir($rulesDirectory);

        if ($entries === false) {
            return [];
        }

        $items = [];

        foreach ($entries as $entry) {
            if (! str_ends_with($entry, '.mdc')) {
                continue;
            }

            foreach ($this->instructionFileMetadata($cwd, Path::join('.cursor/rules', $entry)) as $metadata) {
                $items[] = $metadata;
            }
        }

        return $items;
    }

    private function instructionFilePath(string $targetName): string
    {
        return match ($targetName) {
            'claude-code' => 'CLAUDE.md',
            'github-copilot' => '.github/copilot-instructions.md',
            'gemini' => 'GEMINI.md',
            default => 'AGENTS.md',
        };
    }

    /**
     * @return list<SkillManagedMetadata>
     */
    private function instructionFileMetadata(string $cwd, string $relativePath): array
    {
        try {
            $path = (new PathGuard($cwd))->assertInside(Path::join($cwd, $relativePath));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $relativePath],
            );
        }

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not read target file "%s".', $path),
                context: ['path' => $path],
            );
        }

        return $this->blockEditor->metadata($contents);
    }
}
