<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Sift\Skills\Skill;

interface InstructionTarget
{
    public function name(): string;

    /**
     * @param array<string, mixed> $metadata
     */
    public function install(string $cwd, Skill $skill, array $metadata): SkillTargetInstallResult;

    public function remove(string $cwd, string $skillName): SkillTargetRemoveResult;
}
