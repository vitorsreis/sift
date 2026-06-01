<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;
use Sift\Skills\Targets\InstructionTargetRegistry;

final readonly class SkillInventory
{
    public function __construct(
        private ManagedBlockEditor $blockEditor = new ManagedBlockEditor(),
        private InstructionTargetRegistry $targetRegistry = new InstructionTargetRegistry(),
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
            $this->targetRegistry->resolve($target);

            if ($target !== 'generic') {
                continue;
            }

            foreach ($this->instructionFileMetadata($cwd, 'AGENTS.md') as $metadata) {
                if (in_array($target, $metadata->targets(), true)) {
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
