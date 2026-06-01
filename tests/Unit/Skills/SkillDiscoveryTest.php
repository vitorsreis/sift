<?php

declare(strict_types=1);

use Sift\Skills\Skill;
use Sift\Skills\SkillDiscovery;
use Tests\Support\FixtureProject;

it('discovers root, repository, agents and direct child skills', function (): void {
    $project = FixtureProject::create();
    skillDiscoveryFixture($project, 'SKILL.md', 'root-skill');
    skillDiscoveryFixture($project, 'skills/php/SKILL.md', 'php-skill');
    skillDiscoveryFixture($project, '.agents/skills/codex/SKILL.md', 'codex-skill');
    skillDiscoveryFixture($project, 'review/SKILL.md', 'review-skill');

    $skills = (new SkillDiscovery())->discover($project->root(), 'local-source', 'local');

    expect(array_map(static fn(Skill $skill): string => $skill->name(), $skills))->toBe([
        'codex-skill',
        'php-skill',
        'review-skill',
        'root-skill',
    ]);
});

function skillDiscoveryFixture(FixtureProject $project, string $path, string $name): void
{
    $project->write($path, sprintf(
        <<<'MD'
---
name: %s
description: Use %s.
---

# %s
MD,
        $name,
        $name,
        $name,
    ));
}
