<?php

declare(strict_types=1);

use Tests\Support\CliRunner;
use Tests\Support\FixtureProject;

it('lists discovered skills without writing targets', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.');
    skillsCommandFixture($repository, 'skills/laravel-review/SKILL.md', 'laravel-review', 'Use when reviewing Laravel.');

    $result = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--list',
        '--global',
        '--agent=generic',
        '--yes',
        '--all',
    ], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $meta = skillsCommandObject($payload, 'meta');

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($payload['tool'] ?? null)->toBe('sift');
    expect($payload['status'] ?? null)->toBe('passed');
    expect(skillsCommandObject($payload, 'summary')['total'] ?? null)->toBe(2);
    expect(array_column(skillsCommandItems($payload), 'name'))->toBe(['laravel-review', 'php-review']);
    expect($meta['subcommand'] ?? null)->toBe('skills add --list');
    expect($meta['global'] ?? null)->toBeTrue();
    expect($meta['warnings'] ?? null)->toBe(['local_source']);
    expect($meta['ignored_options'] ?? null)->toBe(['agent', 'yes', 'all']);
    expect($project->path('AGENTS.md'))->not->toBeFile();
});

it('lists the bundled sift skill', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'add', 'sift', '--list'], $project->root());
    $payload = CliRunner::decode($result['stdout']);

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['total'] ?? null)->toBe(1);
    expect(skillsCommandItems($payload)[0]['name'] ?? null)->toBe('sift');
});

it('renders skills command usage errors as json', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['--json', '--no-pretty', 'skills', 'add', 'sift'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = skillsCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(3);
    expect($result['stdout'])->toBe('');
    expect($payload['status'] ?? null)->toBe('error');
    expect($error['code'] ?? null)->toBe('invalid_usage');
    expect($error['message'] ?? null)->toBe('Mutating skill commands require --yes or --all in non-interactive mode.');
});

it('requires an explicit target when installing with yes', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['--json', '--no-pretty', 'skills', 'add', 'sift', '--yes'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = skillsCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(3);
    expect($result['stdout'])->toBe('');
    expect($error['code'] ?? null)->toBe('invalid_usage');
    expect($error['message'] ?? null)->toBe('skills add requires --agent or --all when installing.');
});

it('installs a selected skill into the generic agents file', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.');
    skillsCommandFixture($repository, 'skills/laravel-review/SKILL.md', 'laravel-review', 'Use when reviewing Laravel.');
    $project->write('AGENTS.md', "Manual instructions\n");

    $result = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--skill=php-review',
        '--agent=generic',
        '--yes',
    ], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));
    $items = skillsCommandItems($payload);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect(skillsCommandObject($payload, 'summary')['installed'] ?? null)->toBe(1);
    expect($items[0]['name'] ?? null)->toBe('php-review');
    expect($items[0]['target'] ?? null)->toBe('generic');
    expect($agents)->toContain('Manual instructions');
    expect($agents)->toContain('<!-- sift:skill:php-review:start data="');
    expect($agents)->toContain('name: php-review');
    expect($agents)->not->toContain('name: laravel-review');
});

it('installs a single skill source without an explicit skill selector', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');

    $result = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--agent=generic',
        '--yes',
    ], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['installed'] ?? null)->toBe(1);
    expect($agents)->toContain('name: php-review');
});

it('installs codex skills into the project by default and global scope with --global', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    $codexHome = FixtureProject::create('sift-codex-home-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');
    $previousCodexHome = getenv('CODEX_HOME');
    putenv('CODEX_HOME=' . $codexHome->root());

    try {
        $projectResult = CliRunner::run([
            '--json',
            '--full',
            '--no-pretty',
            'skills',
            'add',
            $repository->root(),
            '--agent=codex',
            '--yes',
        ], $project->root());

        $globalResult = CliRunner::run([
            '--json',
            '--full',
            '--no-pretty',
            'skills',
            'add',
            $repository->root(),
            '--agent=codex',
            '--global',
            '--yes',
        ], $project->root());

        $projectList = CliRunner::decode(CliRunner::run([
            '--json',
            '--full',
            '--no-pretty',
            'skills',
            'list',
            '--agent=codex',
        ], $project->root())['stdout']);

        $globalList = CliRunner::decode(CliRunner::run([
            '--json',
            '--full',
            '--no-pretty',
            'skills',
            'list',
            '--agent=codex',
            '--global',
        ], $project->root())['stdout']);
    } finally {
        putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
    }

    expect($projectResult['exit_code'])->toBe(0);
    expect($globalResult['exit_code'])->toBe(0);
    expect($project->path('.agents/skills/php-review/SKILL.md'))->toBeFile();
    expect($codexHome->path('skills/php-review/SKILL.md'))->toBeFile();
    expect(skillsCommandObject($projectList, 'summary')['total'] ?? null)->toBe(1);
    expect(skillsCommandObject($globalList, 'summary')['total'] ?? null)->toBe(1);
});

it('installs every discovered skill with the wildcard selector', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.');
    skillsCommandFixture($repository, 'skills/laravel-review/SKILL.md', 'laravel-review', 'Use when reviewing Laravel.');

    $result = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--skill=*',
        '--agent=generic',
        '--yes',
    ], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['installed'] ?? null)->toBe(2);
    expect($agents)->toContain('name: php-review');
    expect($agents)->toContain('name: laravel-review');
});

it('installs repeated skill selectors', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.');
    skillsCommandFixture($repository, 'skills/laravel-review/SKILL.md', 'laravel-review', 'Use when reviewing Laravel.');
    skillsCommandFixture($repository, 'skills/security-review/SKILL.md', 'security-review', 'Use when reviewing security.');

    $result = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--skill=php-review',
        '--skill',
        'laravel-review',
        '--agent=generic',
        '--yes',
    ], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['installed'] ?? null)->toBe(2);
    expect($agents)->toContain('name: php-review');
    expect($agents)->toContain('name: laravel-review');
    expect($agents)->not->toContain('name: security-review');
});

it('installs npx skills style multi value skills and agents', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.');
    skillsCommandFixture($repository, 'skills/laravel-review/SKILL.md', 'laravel-review', 'Use when reviewing Laravel.');
    skillsCommandFixture($repository, 'skills/security-review/SKILL.md', 'security-review', 'Use when reviewing security.');

    $result = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'a',
        $repository->root(),
        '--skill',
        'php-review',
        'laravel-review',
        '--agent',
        'generic',
        'codex',
        '--yes',
    ], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary'))->toMatchArray(['installed' => 4, 'skills' => 2, 'targets' => 2]);
    expect(skillsCommandObject($payload, 'meta')['targets'] ?? null)->toBe(['generic', 'codex']);
    expect($agents)->toContain('name: php-review');
    expect($agents)->toContain('name: laravel-review');
    expect($agents)->not->toContain('name: security-review');
    expect($project->path('.agents/skills/php-review/SKILL.md'))->toBeFile();
    expect($project->path('.agents/skills/laravel-review/SKILL.md'))->toBeFile();
    expect($project->path('.agents/skills/security-review/SKILL.md'))->not->toBeFile();
});

it('generates a skills use prompt without installing the skill', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixtureWithBody($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Read the PHP review checklist.');
    $repository->write('skills/php-review/references/checklist.md', "Checklist body\n");

    $result = CliRunner::run([
        'skills',
        'use',
        $repository->root(),
        '--skill',
        'php-review',
        '--no-color',
    ], $project->root());

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->toContain('You are being given a Skill to execute');
    expect($result['stdout'])->toContain('<SKILL.md>');
    expect($result['stdout'])->toContain('name: php-review');
    expect($result['stdout'])->toContain('Read the PHP review checklist.');
    expect($result['stdout'])->toContain('Supporting files for this skill were downloaded to:');
    expect($project->path('AGENTS.md'))->not->toBeFile();
    expect($project->path('.agents/skills/php-review/SKILL.md'))->not->toBeFile();
});

it('installs and removes Eve subagent skills with the npx subagent option', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');

    $add = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--subagent',
        'reviewer',
        '--yes',
    ], $project->root());
    $addPayload = CliRunner::decode($add['stdout']);

    $list = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'list',
        '--agent',
        'eve:reviewer',
    ], $project->root());
    $listPayload = CliRunner::decode($list['stdout']);

    expect($add['exit_code'])->toBe(0);
    expect(skillsCommandObject($addPayload, 'meta')['targets'] ?? null)->toBe(['eve:reviewer']);
    expect($project->path('agent/subagents/reviewer/skills/php-review/SKILL.md'))->toBeFile();
    expect($project->path('agent/skills/php-review/SKILL.md'))->not->toBeFile();
    expect(skillsCommandObject($listPayload, 'summary')['total'] ?? null)->toBe(1);
    expect(skillsCommandItems($listPayload)[0]['targets'] ?? null)->toBe(['eve:reviewer']);

    $remove = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'remove',
        'php-review',
        '--agent',
        'eve:reviewer',
        '--yes',
    ], $project->root());
    $removePayload = CliRunner::decode($remove['stdout']);

    expect(skillsCommandObject($removePayload, 'summary')['removed'] ?? null)->toBe(1);
    expect($project->path('agent/subagents/reviewer/skills/php-review/SKILL.md'))->not->toBeFile();
});

it('lists installed generic skills from managed blocks only', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');
    $project->write('AGENTS.md', "Manual mention of php-review without managed metadata\n");

    $emptyList = CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'list', '--agent=generic'], $project->root());
    $emptyPayload = CliRunner::decode($emptyList['stdout']);

    CliRunner::run([
        '--json',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--agent=generic',
        '--yes',
    ], $project->root());

    $result = CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'list', '--agent=generic'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $items = skillsCommandItems($payload);

    expect($emptyList['exit_code'])->toBe(0);
    expect(skillsCommandObject($emptyPayload, 'summary')['total'] ?? null)->toBe(0);
    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['total'] ?? null)->toBe(1);
    expect($items[0]['name'] ?? null)->toBe('php-review');
    expect($items[0]['targets'] ?? null)->toBe(['generic']);
});

it('removes only managed generic skill blocks', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');
    $project->write('AGENTS.md', "Manual instructions\n");

    CliRunner::run([
        '--json',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--agent=generic',
        '--yes',
    ], $project->root());

    $result = CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'remove', 'php-review', '--agent=generic', '--yes'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));
    $list = CliRunner::decode(CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'list', '--agent=generic'], $project->root())['stdout']);

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['removed'] ?? null)->toBe(1);
    expect($agents)->toContain('Manual instructions');
    expect($agents)->not->toContain('sift:skill:php-review:start');
    expect(skillsCommandObject($list, 'summary')['total'] ?? null)->toBe(0);
});

it('removes managed project codex skill directories', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    $codexHome = FixtureProject::create('sift-codex-home-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');
    $previousCodexHome = getenv('CODEX_HOME');
    putenv('CODEX_HOME=' . $codexHome->root());

    try {
        CliRunner::run([
            '--json',
            '--no-pretty',
            'skills',
            'add',
            $repository->root(),
            '--agent=codex',
            '--yes',
        ], $project->root());

        $result = CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'remove', 'php-review', '--agent=codex', '--yes'], $project->root());
        $payload = CliRunner::decode($result['stdout']);
        $items = skillsCommandItems($payload);
        $list = CliRunner::decode(CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'list', '--agent=codex'], $project->root())['stdout']);
    } finally {
        putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
    }

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['removed'] ?? null)->toBe(1);
    expect($items[0]['target'] ?? null)->toBe('codex');
    expect($items[0]['action'] ?? null)->toBe('removed');
    expect($project->path('.agents/skills/php-review'))->not->toBeDirectory();
    expect($codexHome->path('skills/php-review'))->not->toBeDirectory();
    expect(skillsCommandObject($list, 'summary')['total'] ?? null)->toBe(0);
});

it('does not remove unmanaged project codex skill directories', function (): void {
    $project = FixtureProject::create();
    $project->write('.agents/skills/php-review/SKILL.md', "# PHP Review\n");

    $result = CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'remove', 'php-review', '--agent=codex', '--yes'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $items = skillsCommandItems($payload);

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['removed'] ?? null)->toBe(0);
    expect($items[0]['target'] ?? null)->toBe('codex');
    expect($items[0]['action'] ?? null)->toBe('missing');
    expect($project->path('.agents/skills/php-review/SKILL.md'))->toBeFile();
});

it('requires confirmation before removing skills', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['--json', '--no-pretty', 'skills', 'remove', 'php-review', '--agent=generic'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = skillsCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(3);
    expect($error['code'] ?? null)->toBe('invalid_usage');
});

it('updates an installed generic skill from managed source metadata', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Old guidance');
    $project->write('AGENTS.md', "Manual instructions\n");

    CliRunner::run([
        '--json',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--agent=generic',
        '--yes',
    ], $project->root());

    skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Updated guidance');

    $result = CliRunner::run(['--json', '--full', '--no-pretty', 'skills', 'update', 'php-review', '--agent=generic', '--yes'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['updated'] ?? null)->toBe(1);
    expect(skillsCommandItems($payload)[0]['action'] ?? null)->toBe('updated');
    expect($agents)->toContain('Manual instructions');
    expect($agents)->toContain('Updated guidance');
    expect($agents)->not->toContain('Old guidance');
});

it('updates project skills with the npx skills upgrade alias and project scope', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Old project guidance');

    CliRunner::run([
        '--json',
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--agent',
        'generic',
        '--yes',
    ], $project->root());

    skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Updated project guidance');

    $result = CliRunner::run([
        '--json',
        '--full',
        '--no-pretty',
        'skills',
        'upgrade',
        'php-review',
        '--project',
        '--agent',
        'generic',
        '--yes',
    ], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['updated'] ?? null)->toBe(1);
    expect(skillsCommandObject($payload, 'meta'))->toMatchArray(['global' => false]);
    expect($agents)->toContain('Updated project guidance');
    expect($agents)->not->toContain('Old project guidance');
});

it('updates named project and global skills when no update scope is specified', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    $codexHome = FixtureProject::create('sift-codex-home-');
    skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Old both guidance');
    $previousCodexHome = getenv('CODEX_HOME');
    putenv('CODEX_HOME=' . $codexHome->root());

    try {
        CliRunner::run([
            '--json',
            '--no-pretty',
            'skills',
            'add',
            $repository->root(),
            '--agent',
            'codex',
            '--yes',
        ], $project->root());
        CliRunner::run([
            '--json',
            '--no-pretty',
            'skills',
            'add',
            $repository->root(),
            '--agent',
            'codex',
            '--global',
            '--yes',
        ], $project->root());

        skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Updated both guidance');

        $result = CliRunner::run([
            '--json',
            '--full',
            '--no-pretty',
            'skills',
            'upgrade',
            'php-review',
            '--agent',
            'codex',
            '--yes',
        ], $project->root());
        $payload = CliRunner::decode($result['stdout']);
    } finally {
        putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
    }

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary'))->toMatchArray(['updated' => 2, 'skills' => 2, 'targets' => 1]);
    expect(skillsCommandObject($payload, 'meta'))->toMatchArray(['scope' => 'both']);
    expect((string) file_get_contents($project->path('.agents/skills/php-review/SKILL.md')))->toContain('Updated both guidance');
    expect((string) file_get_contents($codexHome->path('skills/php-review/SKILL.md')))->toContain('Updated both guidance');
});

it('updates global skills with yes when the project has no skills', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    $codexHome = FixtureProject::create('sift-codex-home-');
    skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Old global guidance');
    $previousCodexHome = getenv('CODEX_HOME');
    putenv('CODEX_HOME=' . $codexHome->root());

    try {
        CliRunner::run([
            '--json',
            '--no-pretty',
            'skills',
            'add',
            $repository->root(),
            '--agent',
            'codex',
            '--global',
            '--yes',
        ], $project->root());

        skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Updated global guidance');

        $result = CliRunner::run([
            '--json',
            '--full',
            '--no-pretty',
            'skills',
            'update',
            '--agent',
            'codex',
            '--yes',
        ], $project->root());
        $payload = CliRunner::decode($result['stdout']);
    } finally {
        putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
    }

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary'))->toMatchArray(['updated' => 1, 'skills' => 1, 'targets' => 1]);
    expect(skillsCommandObject($payload, 'meta'))->toMatchArray(['global' => true, 'scope' => 'global']);
    expect((string) file_get_contents($codexHome->path('skills/php-review/SKILL.md')))->toContain('Updated global guidance');
    expect($project->path('.agents/skills/php-review/SKILL.md'))->not->toBeFile();
});

it('requires confirmation before updating skills', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['--json', '--no-pretty', 'skills', 'update', 'php-review', '--agent=generic'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = skillsCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(3);
    expect($error['code'] ?? null)->toBe('invalid_usage');
});

it('fails mutating skill commands when the target lock is held', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');
    $lockPath = $project->write('.sift/locks/skills/generic.lock', '');
    $handle = fopen($lockPath, 'c+b');

    if (! is_resource($handle)) {
        throw new RuntimeException('Could not open lock fixture.');
    }

    flock($handle, LOCK_EX);

    try {
        $result = CliRunner::run(['--json', '--no-pretty', 'skills', 'add', $repository->root(), '--agent=generic', '--yes'], $project->root());
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    $payload = CliRunner::decode($result['stderr']);
    $error = skillsCommandObject($payload, 'error');

    expect($error['code'] ?? null)->toBe('filesystem_error');
    expect($project->path('AGENTS.md'))->not->toBeFile();
});

it('requires confirmation before writing skills in non interactive mode', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');

    $result = CliRunner::run(['--json', '--no-pretty', 'skills', 'add', $repository->root(), '--agent=generic'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = skillsCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(3);
    expect($result['stdout'])->toBe('');
    expect($project->path('AGENTS.md'))->not->toBeFile();
    expect($error['code'] ?? null)->toBe('invalid_usage');
});

it('does not install ambiguous multi skill sources without an explicit skill selector', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.');
    skillsCommandFixture($repository, 'skills/laravel-review/SKILL.md', 'laravel-review', 'Use when reviewing Laravel.');

    $result = CliRunner::run(['--json', '--no-pretty', 'skills', 'add', $repository->root(), '--agent=generic', '--yes'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = skillsCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(3);
    expect($project->path('AGENTS.md'))->not->toBeFile();
    expect($error['code'] ?? null)->toBe('skill_selection_required');
});

function skillsCommandFixture(FixtureProject $project, string $path, string $name, string $description): void
{
    skillsCommandFixtureWithBody($project, $path, $name, $description, '');
}

function skillsCommandFixtureWithBody(FixtureProject $project, string $path, string $name, string $description, string $body): void
{
    $project->write($path, sprintf(
        <<<'MD'
---
name: %s
description: %s
---

# %s

%s
MD,
        $name,
        $description,
        $name,
        $body,
    ));
}

/**
 * @param array<string, mixed> $payload
 *
 * @return array<string, mixed>
 */
function skillsCommandObject(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value) || array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected object field "%s".', $key));
    }

    $object = [];

    foreach ($value as $field => $fieldValue) {
        if (! is_string($field)) {
            throw new RuntimeException(sprintf('Expected string keys in "%s".', $key));
        }

        $object[$field] = $fieldValue;
    }

    return $object;
}

/**
 * @param array<string, mixed> $payload
 *
 * @return list<array<string, mixed>>
 */
function skillsCommandItems(array $payload): array
{
    $items = $payload['items'] ?? null;

    if (! is_array($items) || ! array_is_list($items)) {
        throw new RuntimeException('Expected payload items list.');
    }

    $normalized = [];

    foreach ($items as $item) {
        if (! is_array($item) || array_is_list($item)) {
            throw new RuntimeException('Expected item object.');
        }

        $object = [];

        foreach ($item as $key => $value) {
            if (is_string($key)) {
                $object[$key] = $value;
            }
        }

        $normalized[] = $object;
    }

    return $normalized;
}
