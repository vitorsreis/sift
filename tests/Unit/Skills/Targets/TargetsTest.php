<?php

declare(strict_types=1);

use Sift\Skills\Skill;
use Sift\Skills\SkillSource;
use Sift\Skills\Targets\SkillTargetInstaller;
use Tests\Support\FixtureProject;

it('installs managed blocks into stable instruction file targets', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-skill-source-');
    $skillFile = $source->write('SKILL.md', <<<'MD'
---
name: php-review
description: Review PHP projects.
---

# PHP Review
MD);
    $skill = new Skill(
        name: 'php-review',
        description: 'Review PHP projects.',
        path: $source->root(),
        skillFile: $skillFile,
        source: 'vendor/source',
        sourceType: 'local',
    );

    $results = (new SkillTargetInstaller())->install(
        $project->root(),
        [$skill],
        ['generic', 'claude-code', 'github-copilot', 'gemini'],
        new SkillSource('vendor/source', 'local', $source->root()),
    );

    expect($results)->toHaveCount(4);
    expect((string) file_get_contents($project->path('AGENTS.md')))->toContain('sift:skill:php-review:start');
    expect((string) file_get_contents($project->path('CLAUDE.md')))->toContain('sift:skill:php-review:start');
    expect((string) file_get_contents($project->path('.github/copilot-instructions.md')))->toContain('sift:skill:php-review:start');
    expect((string) file_get_contents($project->path('GEMINI.md')))->toContain('sift:skill:php-review:start');
});
