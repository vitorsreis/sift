<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Sift\Skills\Skill;

final readonly class GeminiInstructionTarget implements InstructionTarget
{
    public function __construct(
        private InstructionFileTarget $target = new InstructionFileTarget('gemini', 'GEMINI.md'),
    ) {}

    public function name(): string
    {
        return $this->target->name();
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function install(string $cwd, Skill $skill, array $metadata): SkillTargetInstallResult
    {
        return $this->target->install($cwd, $skill, $metadata);
    }

    public function remove(string $cwd, string $skillName): SkillTargetRemoveResult
    {
        return $this->target->remove($cwd, $skillName);
    }
}
