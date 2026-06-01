<?php

declare(strict_types=1);

use Sift\Console\InvalidUsageException;
use Sift\Skills\SkillScaffolder;
use Tests\Support\FixtureProject;

it('creates a named skill directory with a normalized SKILL.md', function (): void {
    $project = FixtureProject::create();

    $result = (new SkillScaffolder())->scaffold($project->root(), 'Laravel Review');
    $contents = (string) file_get_contents($project->path('laravel-review/SKILL.md'));

    expect($result['name'])->toBe('laravel-review');
    expect($result['path'])->toBe($project->path('laravel-review/SKILL.md'));
    expect($contents)->toContain('name: laravel-review');
    expect($contents)->toContain('description: Use when working with laravel review.');
});

it('creates SKILL.md in the current directory when no name is given', function (): void {
    $project = FixtureProject::create();
    $cwd = $project->mkdir('PHP Review');

    $result = (new SkillScaffolder())->scaffold($cwd, null);
    $contents = (string) file_get_contents($project->path('PHP Review/SKILL.md'));

    expect($result['name'])->toBe('php-review');
    expect($contents)->toContain('name: php-review');
});

it('rejects invalid inferred skill names', function (): void {
    $project = FixtureProject::create();

    expect(fn(): array => (new SkillScaffolder())->scaffold($project->root(), '!!!'))
        ->toThrow(InvalidUsageException::class, 'Unable to infer a valid skill name.');
});

it('does not overwrite an existing skill without confirmation', function (): void {
    $project = FixtureProject::create();
    $cwd = $project->mkdir('Sift Test');
    $project->write('Sift Test/SKILL.md', 'existing');

    expect(fn(): array => (new SkillScaffolder())->scaffold($cwd, null))
        ->toThrow(InvalidUsageException::class, 'SKILL.md already exists.');

    (new SkillScaffolder())->scaffold($cwd, null, overwrite: true);

    expect((string) file_get_contents($project->path('Sift Test/SKILL.md')))->toContain('name: sift-test');
});
