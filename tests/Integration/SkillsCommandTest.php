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

    $result = CliRunner::run(['--full', '--no-pretty', 'skills', 'add', 'sift', '--list'], $project->root());
    $payload = CliRunner::decode($result['stdout']);

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['total'] ?? null)->toBe(1);
    expect(skillsCommandItems($payload)[0]['name'] ?? null)->toBe('sift');
});

it('renders skills command usage errors as json', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['--no-pretty', 'skills', 'add', 'sift'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = skillsCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(3);
    expect($result['stdout'])->toBe('');
    expect($payload['status'] ?? null)->toBe('error');
    expect($error['code'] ?? null)->toBe('invalid_usage');
    expect($error['message'] ?? null)->toBe('Mutating skill commands require --yes or --all in non-interactive mode.');
});

it('installs a selected skill into the generic agents file', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.');
    skillsCommandFixture($repository, 'skills/laravel-review/SKILL.md', 'laravel-review', 'Use when reviewing Laravel.');
    $project->write('AGENTS.md', "Manual instructions\n");

    $result = CliRunner::run([
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

it('installs every discovered skill with the wildcard selector', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'skills/php-review/SKILL.md', 'php-review', 'Use when reviewing PHP.');
    skillsCommandFixture($repository, 'skills/laravel-review/SKILL.md', 'laravel-review', 'Use when reviewing Laravel.');

    $result = CliRunner::run([
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

it('lists installed generic skills from managed blocks only', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');
    $project->write('AGENTS.md', "Manual mention of php-review without managed metadata\n");

    $emptyList = CliRunner::run(['--full', '--no-pretty', 'skills', 'list', '--agent=generic'], $project->root());
    $emptyPayload = CliRunner::decode($emptyList['stdout']);

    CliRunner::run([
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--agent=generic',
        '--yes',
    ], $project->root());

    $result = CliRunner::run(['--full', '--no-pretty', 'skills', 'list', '--agent=generic'], $project->root());
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
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--agent=generic',
        '--yes',
    ], $project->root());

    $result = CliRunner::run(['--full', '--no-pretty', 'skills', 'remove', 'php-review', '--agent=generic', '--yes'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));
    $list = CliRunner::decode(CliRunner::run(['--full', '--no-pretty', 'skills', 'list', '--agent=generic'], $project->root())['stdout']);

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['removed'] ?? null)->toBe(1);
    expect($agents)->toContain('Manual instructions');
    expect($agents)->not->toContain('sift:skill:php-review:start');
    expect(skillsCommandObject($list, 'summary')['total'] ?? null)->toBe(0);
});

it('removes managed codex skill directories', function (): void {
    $project = FixtureProject::create();
    $repository = FixtureProject::create('sift-skills-repo-');
    $codexHome = FixtureProject::create('sift-codex-home-');
    skillsCommandFixture($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.');
    $previousCodexHome = getenv('SIFT_CODEX_HOME');
    putenv('SIFT_CODEX_HOME=' . $codexHome->root());

    try {
        CliRunner::run([
            '--no-pretty',
            'skills',
            'add',
            $repository->root(),
            '--agent=codex',
            '--yes',
        ], $project->root());

        $result = CliRunner::run(['--full', '--no-pretty', 'skills', 'remove', 'php-review', '--agent=codex', '--yes'], $project->root());
        $payload = CliRunner::decode($result['stdout']);
        $items = skillsCommandItems($payload);
        $list = CliRunner::decode(CliRunner::run(['--full', '--no-pretty', 'skills', 'list', '--agent=codex'], $project->root())['stdout']);
    } finally {
        putenv($previousCodexHome === false ? 'SIFT_CODEX_HOME' : 'SIFT_CODEX_HOME=' . $previousCodexHome);
    }

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['removed'] ?? null)->toBe(1);
    expect($items[0]['target'] ?? null)->toBe('codex');
    expect($items[0]['action'] ?? null)->toBe('removed');
    expect($codexHome->path('skills/php-review'))->not->toBeDirectory();
    expect(skillsCommandObject($list, 'summary')['total'] ?? null)->toBe(0);
});

it('does not remove unmanaged codex skill directories', function (): void {
    $project = FixtureProject::create();
    $codexHome = FixtureProject::create('sift-codex-home-');
    $codexHome->write('skills/php-review/SKILL.md', "# PHP Review\n");

    $previousCodexHome = getenv('SIFT_CODEX_HOME');
    putenv('SIFT_CODEX_HOME=' . $codexHome->root());

    try {
        $result = CliRunner::run(['--full', '--no-pretty', 'skills', 'remove', 'php-review', '--agent=codex', '--yes'], $project->root());
        $payload = CliRunner::decode($result['stdout']);
        $items = skillsCommandItems($payload);
    } finally {
        putenv($previousCodexHome === false ? 'SIFT_CODEX_HOME' : 'SIFT_CODEX_HOME=' . $previousCodexHome);
    }

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['removed'] ?? null)->toBe(0);
    expect($items[0]['target'] ?? null)->toBe('codex');
    expect($items[0]['action'] ?? null)->toBe('missing');
    expect($codexHome->path('skills/php-review/SKILL.md'))->toBeFile();
});

it('requires confirmation before removing skills', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['--no-pretty', 'skills', 'remove', 'php-review', '--agent=generic'], $project->root());
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
        '--no-pretty',
        'skills',
        'add',
        $repository->root(),
        '--agent=generic',
        '--yes',
    ], $project->root());

    skillsCommandFixtureWithBody($repository, 'SKILL.md', 'php-review', 'Use when reviewing PHP.', 'Updated guidance');

    $result = CliRunner::run(['--full', '--no-pretty', 'skills', 'update', 'php-review', '--agent=generic', '--yes'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $agents = (string) file_get_contents($project->path('AGENTS.md'));

    expect($result['exit_code'])->toBe(0);
    expect(skillsCommandObject($payload, 'summary')['updated'] ?? null)->toBe(1);
    expect(skillsCommandItems($payload)[0]['action'] ?? null)->toBe('updated');
    expect($agents)->toContain('Manual instructions');
    expect($agents)->toContain('Updated guidance');
    expect($agents)->not->toContain('Old guidance');
});

it('requires confirmation before updating skills', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['--no-pretty', 'skills', 'update', 'php-review', '--agent=generic'], $project->root());
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
        $result = CliRunner::run(['--no-pretty', 'skills', 'add', $repository->root(), '--agent=generic', '--yes'], $project->root());
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

    $result = CliRunner::run(['--no-pretty', 'skills', 'add', $repository->root(), '--agent=generic'], $project->root());
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

    $result = CliRunner::run(['--no-pretty', 'skills', 'add', $repository->root(), '--agent=generic', '--yes'], $project->root());
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
