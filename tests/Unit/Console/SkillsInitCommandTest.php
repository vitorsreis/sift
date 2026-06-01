<?php

declare(strict_types=1);

use Sift\Console\CommandRoute;
use Sift\Console\Commands\SkillsInitCommand;
use Tests\Support\FixtureProject;

it('creates a local skill scaffold from the command route', function (): void {
    $project = FixtureProject::create();

    $payload = (new SkillsInitCommand())->handle(new CommandRoute('skills.init', ['PHP Review']), $project->root());

    expect($payload)->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'created' => 1,
        ],
        'items' => [
            [
                'name' => 'php-review',
                'path' => $project->path('php-review/SKILL.md'),
                'action' => 'created',
            ],
        ],
        'meta' => [
            'subcommand' => 'skills init',
        ],
    ]);
    expect($project->path('php-review/SKILL.md'))->toBeFile();
});

it('passes --yes as overwrite confirmation', function (): void {
    $project = FixtureProject::create();
    $project->write('php-review/SKILL.md', 'existing');

    $payload = (new SkillsInitCommand())->handle(
        new CommandRoute('skills.init', ['PHP Review'], ['yes' => true]),
        $project->root(),
    );

    expect($payload)->toMatchArray([
        'summary' => [
            'created' => 1,
        ],
    ]);
    expect((string) file_get_contents($project->path('php-review/SKILL.md')))->toContain('name: php-review');
});
