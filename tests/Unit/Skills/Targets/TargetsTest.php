<?php

declare(strict_types=1);

use Sift\Exceptions\UserFacingException;
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

it('copies codex skills with managed metadata', function (): void {
    $project = FixtureProject::create();
    $codexHome = FixtureProject::create('sift-codex-home-');
    $source = FixtureProject::create('sift-skill-source-');
    $skillFile = $source->write('SKILL.md', <<<'MD'
---
name: php-review
description: Review PHP projects.
---

# PHP Review
MD);
    $source->write('references/checklist.md', "# Checklist\n");
    $skill = new Skill(
        name: 'php-review',
        description: 'Review PHP projects.',
        path: $source->root(),
        skillFile: $skillFile,
        source: 'vendor/source',
        sourceType: 'local',
    );
    putenv('SIFT_CODEX_HOME=' . $codexHome->root());

    try {
        (new SkillTargetInstaller())->install(
            $project->root(),
            [$skill],
            ['codex'],
            new SkillSource('vendor/source', 'local', $source->root()),
        );
    } finally {
        putenv('SIFT_CODEX_HOME');
    }

    expect($codexHome->path('skills/php-review/SKILL.md'))->toBeFile();
    expect($codexHome->path('skills/php-review/references/checklist.md'))->toBeFile();
    expect($codexHome->readJson('skills/php-review/.sift-skill.json'))->toMatchArray([
        'name' => 'php-review',
        'source' => 'vendor/source',
        'source_type' => 'local',
        'targets' => ['codex'],
    ]);
});

if (PHP_OS_FAMILY !== 'Windows') {
    it('rejects nested symlinks in codex skill sources', function (): void {
        $project = FixtureProject::create();
        $codexHome = FixtureProject::create('sift-codex-home-');
        $source = FixtureProject::create('sift-skill-source-');
        $outside = FixtureProject::create('sift-skill-outside-');
        $skillFile = $source->write('SKILL.md', "# PHP Review\n");
        $outsideFile = $outside->write('secret.txt', 'secret');
        $source->write('references/.keep', '');

        if (! @symlink($outsideFile, $source->path('references/secret.txt'))) {
            return;
        }

        $skill = new Skill('php-review', 'Review PHP projects.', $source->root(), $skillFile, 'vendor/source', 'local');
        putenv('SIFT_CODEX_HOME=' . $codexHome->root());

        try {
            expect(fn(): array => (new SkillTargetInstaller())->install(
                $project->root(),
                [$skill],
                ['codex'],
                new SkillSource('vendor/source', 'local', $source->root()),
            ))->toThrow(UserFacingException::class, 'symlink');
        } finally {
            putenv('SIFT_CODEX_HOME');
        }

        expect($codexHome->path('skills/php-review'))->not->toBeDirectory();
    });

    it('replaces existing codex targets without following nested symlinks', function (): void {
        $project = FixtureProject::create();
        $codexHome = FixtureProject::create('sift-codex-home-');
        $source = FixtureProject::create('sift-skill-source-');
        $outside = FixtureProject::create('sift-skill-outside-');
        $skillFile = $source->write('SKILL.md', "# PHP Review\n");
        $source->write('references/escaped.md', 'must not escape');
        $codexHome->write('skills/php-review/SKILL.md', '# Existing');
        $codexHome->write('skills/php-review/.keep', '');

        if (! @symlink($outside->root(), $codexHome->path('skills/php-review/references'))) {
            return;
        }

        $skill = new Skill('php-review', 'Review PHP projects.', $source->root(), $skillFile, 'vendor/source', 'local');
        putenv('SIFT_CODEX_HOME=' . $codexHome->root());

        try {
            (new SkillTargetInstaller())->install(
                $project->root(),
                [$skill],
                ['codex'],
                new SkillSource('vendor/source', 'local', $source->root()),
            );
        } finally {
            putenv('SIFT_CODEX_HOME');
        }

        expect($outside->path('escaped.md'))->not->toBeFile();
        expect($codexHome->path('skills/php-review/references/escaped.md'))->toBeFile();
    });
}

it('writes cursor rules with escaped frontmatter and managed metadata', function (): void {
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
        description: 'Review "PHP": projects.',
        path: $source->root(),
        skillFile: $skillFile,
        source: 'vendor/source',
        sourceType: 'local',
    );

    $results = (new SkillTargetInstaller())->install(
        $project->root(),
        [$skill],
        ['cursor'],
        new SkillSource('vendor/source', 'local', $source->root()),
    );

    $rule = (string) file_get_contents($project->path('.cursor/rules/php-review.mdc'));
    $normalizedRule = str_replace("\r\n", "\n", $rule);

    expect($results)->toHaveCount(1);
    expect($results[0]->toItem()['target'] ?? null)->toBe('cursor');
    expect($normalizedRule)->toStartWith("---\ndescription: \"Review \\\"PHP\\\": projects.\"\nalwaysApply: false\n---\n\n");
    expect($normalizedRule)->toContain('<!-- sift:skill:php-review:start data="');
    expect($normalizedRule)->toContain('name: php-review');
});

it('writes windsurf rules as managed markdown files', function (): void {
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
        ['windsurf'],
        new SkillSource('vendor/source', 'local', $source->root()),
    );

    $rule = (string) file_get_contents($project->path('.windsurf/rules/php-review.md'));

    expect($results)->toHaveCount(1);
    expect($results[0]->toItem()['target'] ?? null)->toBe('windsurf');
    expect($rule)->toContain('<!-- sift:skill:php-review:start data="');
    expect($rule)->toContain('name: php-review');
    expect($rule)->toContain('# PHP Review');
});
