<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Sift\Core\Clock;
use Sift\Core\SystemClock;
use Sift\Skills\Skill;
use Sift\Skills\SkillSource;

final readonly class SkillTargetInstaller
{
    public function __construct(
        private InstructionTargetRegistry $registry = new InstructionTargetRegistry(),
        private Clock $clock = new SystemClock(),
    ) {}

    /**
     * @param list<Skill> $skills
     * @param list<string> $targets
     *
     * @return list<SkillTargetInstallResult>
     */
    public function install(string $cwd, array $skills, array $targets, SkillSource $source, bool $global = false): array
    {
        $results = [];
        $resolvedTargets = $this->resolveTargets($targets, $global);
        $canonicalTargets = array_keys($resolvedTargets);

        foreach ($resolvedTargets as $target) {
            foreach ($skills as $skill) {
                $results[] = $target->install($cwd, $skill, $this->metadata($skill, $source, $canonicalTargets));
            }
        }

        return $results;
    }

    /**
     * @param list<string> $targets
     *
     * @return array<string, InstructionTarget>
     */
    private function resolveTargets(array $targets, bool $global): array
    {
        $resolvedTargets = [];

        foreach ($targets as $targetName) {
            $target = $this->registry->resolve($targetName, $global);
            $resolvedTargets[$target->name()] = $target;
        }

        return $resolvedTargets;
    }

    /**
     * @param list<string> $targets
     *
     * @return array<string, mixed>
     */
    private function metadata(Skill $skill, SkillSource $source, array $targets): array
    {
        return [
            'name' => $skill->name(),
            'source' => $source->source(),
            'source_type' => $source->type(),
            'resolved_ref' => $source->resolvedRef(),
            'installed_at' => $this->clock->now()->format(DATE_ATOM),
            'targets' => $targets,
        ];
    }
}
