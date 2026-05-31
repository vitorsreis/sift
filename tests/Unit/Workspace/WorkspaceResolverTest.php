<?php

declare(strict_types=1);

use Sift\Workspace\WorkspaceResolver;
use Tests\Support\FixtureProject;

it('uses explicit config before discovery', function (): void {
    $project = FixtureProject::create();
    $project->write('composer.json', '{}');
    $project->mkdir('nested');

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))
        ->resolve($project->path('nested'), 'config/custom.sift.json');

    expect($workspace->projectRoot())->toBe($project->path('nested/config'));
    expect($workspace->configPath())->toBe($project->path('nested/config/custom.sift.json'));
    expect($workspace->projectDetected())->toBeFalse();
});

it('discovers sift config before composer and git markers', function (): void {
    $project = FixtureProject::create();
    $project->write('sift.json', '{}');
    $project->write('app/composer.json', '{}');
    $project->mkdir('app/src');

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))
        ->resolve($project->path('app/src'));

    expect($workspace->projectRoot())->toBe($project->root());
    expect($workspace->configPath())->toBe($project->path('sift.json'));
    expect($workspace->projectDetected())->toBeTrue();
});

it('falls back to composer, git, then current directory without creating files', function (): void {
    $project = FixtureProject::create();
    $composerProject = FixtureProject::create();
    $gitProject = FixtureProject::create();
    $plainProject = FixtureProject::create();

    $composerProject->write('composer.json', '{}');
    $composerProject->mkdir('src');

    $gitProject->mkdir('.git');
    $gitProject->mkdir('packages/demo');

    $resolver = new WorkspaceResolver(homeDirectory: $project->path('home'));

    expect($resolver->resolve($composerProject->path('src'))->projectRoot())->toBe($composerProject->root());
    expect($resolver->resolve($gitProject->path('packages/demo'))->projectRoot())->toBe($gitProject->root());
    expect($resolver->resolve($plainProject->root())->projectRoot())->toBe($plainProject->root());
    expect($resolver->resolve($plainProject->root())->projectDetected())->toBeFalse();
    expect($plainProject->path('sift.json'))->not->toBeFile();
});

it('resolves global scope from SIFT_HOME before home directory', function (): void {
    $project = FixtureProject::create();
    $resolver = new WorkspaceResolver(homeDirectory: $project->path('home'));

    $fromEnvironment = $resolver->resolve($project->root(), environment: [
        'SIFT_HOME' => $project->path('custom-home'),
    ]);
    $fromHome = $resolver->resolve($project->root());

    expect($fromEnvironment->globalRoot())->toBe($project->path('custom-home'));
    expect($fromHome->globalRoot())->toBe($project->path('home/.sift'));
});
