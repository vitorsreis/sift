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
    expect($error['message'] ?? null)->toBe('Skill installation is not implemented yet. Use --list to preview skills.');
});

function skillsCommandFixture(FixtureProject $project, string $path, string $name, string $description): void
{
    $project->write($path, sprintf(
        <<<'MD'
---
name: %s
description: %s
---

# %s
MD,
        $name,
        $description,
        $name,
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
