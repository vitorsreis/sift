<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
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
    $githubSkill = $resolver->resolve('owner/repo@php-review', $project->root());
    $githubUrl = $resolver->resolve('https://github.com/owner/repo', $project->root());

    expect($bundled->type())->toBe('bundled');
    expect($bundled->path())->toEndWith('skills' . DIRECTORY_SEPARATOR . 'sift');
    expect($local->type())->toBe('local');
    expect($local->path())->toBe($project->path('custom'));
    expect($local->warnings())->toBe(['local_source']);
    expect($github->type())->toBe('github');
    expect($github->repositoryUrl())->toBe('https://github.com/owner/repo.git');
    expect($github->warnings())->toBe(['unpinned_source']);
    expect($githubSkill->type())->toBe('github');
    expect($githubSkill->source())->toBe('owner/repo@php-review');
    expect($githubSkill->repositoryUrl())->toBe('https://github.com/owner/repo.git');
    expect($githubSkill->requestedSkill())->toBe('php-review');
    expect($githubUrl->repositoryUrl())->toBe('https://github.com/owner/repo.git');
});

it('rejects relative local sources that resolve outside the workspace through symlinks', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        expect(true)->toBeTrue();

        return;
    }

    $project = FixtureProject::create();
    $outside = FixtureProject::create('sift-external-skill-');
    $project->mkdir('links');
    $outside->write('php-review/SKILL.md', <<<'MD'
---
name: php-review
description: Review PHP.
---
MD);

    if (! @symlink($outside->path('php-review'), $project->path('links/php-review'))) {
        expect(true)->toBeTrue();

        return;
    }

    try {
        (new SkillSourceResolver())->resolve('links/php-review', $project->root());
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::PolicyBlocked);

        return;
    }

    throw new RuntimeException('Expected symlinked source to be rejected.');
});
