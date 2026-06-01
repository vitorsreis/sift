<?php

declare(strict_types=1);

use Sift\Skills\SkillSourceResolver;
use Tests\Support\FixtureProject;

it('resolves bundled, local and github sources', function (): void {
    $project = FixtureProject::create();
    $project->write('custom/SKILL.md', <<<'MD'
---
name: custom
description: Custom skill.
---
MD);
    $resolver = new SkillSourceResolver();

    $bundled = $resolver->resolve('sift', $project->root());
    $local = $resolver->resolve('custom', $project->root());
    $github = $resolver->resolve('owner/repo', $project->root());
    $githubUrl = $resolver->resolve('https://github.com/owner/repo', $project->root());

    expect($bundled->type())->toBe('bundled');
    expect($bundled->path())->toEndWith('skills' . DIRECTORY_SEPARATOR . 'sift');
    expect($local->type())->toBe('local');
    expect($local->path())->toBe($project->path('custom'));
    expect($local->warnings())->toBe(['local_source']);
    expect($github->type())->toBe('github');
    expect($github->repositoryUrl())->toBe('https://github.com/owner/repo.git');
    expect($github->warnings())->toBe(['unpinned_source']);
    expect($githubUrl->repositoryUrl())->toBe('https://github.com/owner/repo.git');
});
