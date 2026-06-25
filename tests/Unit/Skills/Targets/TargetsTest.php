<?php

declare(strict_types=1);

use Sift\Exceptions\UserFacingException;
use Sift\Skills\Skill;
use Sift\Skills\SkillSource;
use Sift\Skills\Targets\CodexHomeResolver;
use Sift\Skills\Targets\CodexSkillTarget;
use Sift\Skills\Targets\CursorRuleTarget;
use Sift\Skills\Targets\InstructionFileTarget;
use Sift\Skills\Targets\InstructionTargetRegistry;
use Sift\Skills\Targets\SkillTargetInstaller;
use Sift\Skills\Targets\WindsurfRuleTarget;
use Tests\Support\FixtureProject;

it('installs managed blocks into the generic instruction file target', function (): void {
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
        ['generic'],
        new SkillSource('vendor/source', 'local', $source->root()),
    );

    expect($results)->toHaveCount(1);
    expect((string) file_get_contents($project->path('AGENTS.md')))->toContain('sift:skill:php-review:start');
    expect($project->path('CLAUDE.md'))->not->toBeFile();
    expect($project->path('.github/copilot-instructions.md'))->not->toBeFile();
    expect($project->path('GEMINI.md'))->not->toBeFile();
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
    $previousCodexHome = getenv('CODEX_HOME');
    putenv('CODEX_HOME=' . $codexHome->root());

    try {
        (new SkillTargetInstaller())->install(
            $project->root(),
            [$skill],
            ['codex'],
            new SkillSource('vendor/source', 'local', $source->root()),
        );
    } finally {
        putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
    }

    expect($project->path('.agents/skills/php-review/SKILL.md'))->toBeFile();
    expect($project->path('.agents/skills/php-review/references/checklist.md'))->toBeFile();
    expect($project->readJson('.agents/skills/php-review/.sift-skill.json'))->toMatchArray([
        'name' => 'php-review',
        'source' => 'vendor/source',
        'source_type' => 'local',
        'targets' => ['codex'],
    ]);
    expect($codexHome->path('skills/php-review/SKILL.md'))->not->toBeFile();
});

it('copies codex skills globally when requested', function (): void {
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
    $skill = new Skill(
        name: 'php-review',
        description: 'Review PHP projects.',
        path: $source->root(),
        skillFile: $skillFile,
        source: 'vendor/source',
        sourceType: 'local',
    );
    $previousCodexHome = getenv('CODEX_HOME');
    putenv('CODEX_HOME=' . $codexHome->root());

    try {
        (new SkillTargetInstaller())->install(
            $project->root(),
            [$skill],
            ['codex'],
            new SkillSource('vendor/source', 'local', $source->root()),
            true,
        );
    } finally {
        putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
    }

    expect($codexHome->path('skills/php-review/SKILL.md'))->toBeFile();
    expect($project->path('.agents/skills/php-review/SKILL.md'))->not->toBeFile();
});

it('updates and removes managed codex skill directories', function (): void {
    $codexHome = FixtureProject::create('sift-codex-home-');
    $source = FixtureProject::create('sift-skill-source-');
    $skillFile = $source->write('SKILL.md', "# PHP Review\n");
    $skill = new Skill('php-review', 'Review PHP projects.', $source->root(), $skillFile, 'vendor/source', 'local');
    $target = new CodexSkillTarget(new CodexHomeResolver($codexHome->root()));
    $metadata = [
        'name' => 'php-review',
        'source' => 'vendor/source',
        'source_type' => 'local',
        'resolved_ref' => null,
        'installed_at' => '2026-06-04T00:00:00+00:00',
        'targets' => ['codex'],
    ];

    $installed = $target->install($source->root(), $skill, $metadata);
    $source->write('references/checklist.md', "# Checklist\n");
    $updated = $target->install($source->root(), $skill, $metadata);
    $removed = $target->remove($source->root(), 'php-review');

    expect($installed->toItem()['action'] ?? null)->toBe('installed');
    expect($updated->toItem()['action'] ?? null)->toBe('updated');
    expect($removed->toItem()['action'] ?? null)->toBe('removed');
    expect($codexHome->path('skills/php-review'))->not->toBeDirectory();
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
        $previousCodexHome = getenv('CODEX_HOME');
        putenv('CODEX_HOME=' . $codexHome->root());

        try {
            expect(fn(): array => (new SkillTargetInstaller())->install(
                $project->root(),
                [$skill],
                ['codex'],
                new SkillSource('vendor/source', 'local', $source->root()),
            ))->toThrow(UserFacingException::class, 'symlink');
        } finally {
            putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
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
        $previousCodexHome = getenv('CODEX_HOME');
        putenv('CODEX_HOME=' . $codexHome->root());

        try {
            (new SkillTargetInstaller())->install(
                $project->root(),
                [$skill],
                ['codex'],
                new SkillSource('vendor/source', 'local', $source->root()),
            );
        } finally {
            putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
        }

        expect($outside->path('escaped.md'))->not->toBeFile();
        expect($codexHome->path('skills/php-review/references/escaped.md'))->toBeFile();
    });
}

it('copies cursor skills with managed metadata', function (): void {
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

    expect($results)->toHaveCount(1);
    expect($results[0]->toItem()['target'] ?? null)->toBe('cursor');
    expect($project->path('.agents/skills/php-review/SKILL.md'))->toBeFile();
    expect($project->readJson('.agents/skills/php-review/.sift-skill.json'))->toMatchArray([
        'name' => 'php-review',
        'targets' => ['cursor'],
    ]);
});

it('merges metadata when multiple targets share the same skills directory', function (): void {
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
    $installer = new SkillTargetInstaller();
    $skillSource = new SkillSource('vendor/source', 'local', $source->root());

    $installer->install($project->root(), [$skill], ['codex'], $skillSource);
    $installer->install($project->root(), [$skill], ['cursor'], $skillSource);

    expect($project->readJson('.agents/skills/php-review/.sift-skill.json'))->toMatchArray([
        'name' => 'php-review',
        'targets' => ['codex', 'cursor'],
    ]);
});

it('removes one shared directory target without deleting other target metadata', function (): void {
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

    (new SkillTargetInstaller())->install(
        $project->root(),
        [$skill],
        ['codex', 'cursor'],
        new SkillSource('vendor/source', 'local', $source->root()),
    );

    $removed = (new InstructionTargetRegistry())
        ->resolve('codex')
        ->remove($project->root(), 'php-review');

    expect($removed->toItem()['action'] ?? null)->toBe('removed');
    expect($project->path('.agents/skills/php-review/SKILL.md'))->toBeFile();
    expect($project->readJson('.agents/skills/php-review/.sift-skill.json'))->toMatchArray([
        'name' => 'php-review',
        'targets' => ['cursor'],
    ]);
});

it('updates and removes managed cursor rules', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-skill-source-');
    $skillFile = $source->write('SKILL.md', "# PHP Review\n");
    $skill = new Skill('php-review', 'Review PHP projects.', $source->root(), $skillFile, 'vendor/source', 'local');
    $target = new CursorRuleTarget();
    $metadata = targetTestMetadata('php-review', ['cursor']);

    $installed = $target->install($project->root(), $skill, $metadata);
    $source->write('SKILL.md', "# PHP Review\n\nUpdated.\n");
    $updated = $target->install($project->root(), $skill, $metadata);
    $removed = $target->remove($project->root(), 'php-review');

    expect($installed->toItem()['action'] ?? null)->toBe('installed');
    expect($updated->toItem()['action'] ?? null)->toBe('updated');
    expect($removed->toItem()['action'] ?? null)->toBe('removed');
    expect($project->path('.cursor/rules/php-review.mdc'))->not->toBeFile();
});

it('copies windsurf skills to its native skills directory', function (): void {
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

    expect($results)->toHaveCount(1);
    expect($results[0]->toItem()['target'] ?? null)->toBe('windsurf');
    expect($project->path('.windsurf/skills/php-review/SKILL.md'))->toBeFile();
    expect($project->readJson('.windsurf/skills/php-review/.sift-skill.json'))->toMatchArray([
        'name' => 'php-review',
        'targets' => ['windsurf'],
    ]);
});

it('updates and removes managed windsurf rules', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-skill-source-');
    $skillFile = $source->write('SKILL.md', "# PHP Review\n");
    $skill = new Skill('php-review', 'Review PHP projects.', $source->root(), $skillFile, 'vendor/source', 'local');
    $target = new WindsurfRuleTarget();
    $metadata = targetTestMetadata('php-review', ['windsurf']);

    $installed = $target->install($project->root(), $skill, $metadata);
    $source->write('SKILL.md', "# PHP Review\n\nUpdated.\n");
    $updated = $target->install($project->root(), $skill, $metadata);
    $removed = $target->remove($project->root(), 'php-review');

    expect($installed->toItem()['action'] ?? null)->toBe('installed');
    expect($updated->toItem()['action'] ?? null)->toBe('updated');
    expect($removed->toItem()['action'] ?? null)->toBe('removed');
    expect($project->path('.windsurf/rules/php-review.md'))->not->toBeFile();
});

it('updates and removes managed instruction file blocks without deleting unmanaged text', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-skill-source-');
    $skillFile = $source->write('SKILL.md', "# PHP Review\n");
    $skill = new Skill('php-review', 'Review PHP projects.', $source->root(), $skillFile, 'vendor/source', 'local');
    $target = new InstructionFileTarget('generic', 'AGENTS.md');
    $metadata = targetTestMetadata('php-review', ['generic']);
    $project->write('AGENTS.md', "# Existing\n");

    $installed = $target->install($project->root(), $skill, $metadata);
    $source->write('SKILL.md', "# PHP Review\n\nUpdated.\n");
    $updated = $target->install($project->root(), $skill, $metadata);
    $removed = $target->remove($project->root(), 'php-review');
    $contents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($installed->toItem()['action'] ?? null)->toBe('installed');
    expect($updated->toItem()['action'] ?? null)->toBe('updated');
    expect($removed->toItem()['action'] ?? null)->toBe('removed');
    expect($contents)->toContain('# Existing');
    expect($contents)->not->toContain('sift:skill:php-review:start');
});

/**
 * @param list<string> $targets
 *
 * @return array<string, mixed>
 */
function targetTestMetadata(string $name, array $targets): array
{
    return [
        'name' => $name,
        'source' => 'vendor/source',
        'source_type' => 'local',
        'resolved_ref' => null,
        'installed_at' => '2026-06-04T00:00:00+00:00',
        'targets' => $targets,
    ];
}
