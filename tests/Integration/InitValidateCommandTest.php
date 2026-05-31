<?php

declare(strict_types=1);

use Sift\Config\ConfigDefaults;
use Tests\Support\CliRunner;
use Tests\Support\FixtureProject;

/**
 * @param array<string, mixed> $payload
 *
 * @return array<string, mixed>
 */
function initValidateObject(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value) || array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected object field "%s".', $key));
    }

    $normalized = [];

    foreach ($value as $field => $fieldValue) {
        if (! is_string($field)) {
            throw new RuntimeException(sprintf('Expected string keys in "%s".', $key));
        }

        $normalized[$field] = $fieldValue;
    }

    return $normalized;
}

it('validates defaults when config is absent', function (): void {
    $project = FixtureProject::create();

    $result = CliRunner::run(['validate'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $summary = initValidateObject($payload, 'summary');
    $meta = initValidateObject($payload, 'meta');

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($payload['tool'] ?? null)->toBe('sift');
    expect($payload['status'] ?? null)->toBe('passed');
    expect(array_key_exists('config_path', $summary))->toBeTrue();
    expect($summary['config_path'])->toBeNull();
    expect($summary['schema'] ?? null)->toBe(ConfigDefaults::schemaUrl());
    expect($summary['using_defaults'] ?? null)->toBeTrue();
    expect($payload['items'] ?? null)->toBe([]);
    expect($meta['subcommand'] ?? null)->toBe('validate');
    expect($project->path('sift.json'))->not->toBeFile();
});

it('initializes a minimal config and validates it', function (): void {
    $project = FixtureProject::create();

    $init = CliRunner::run(['init', '--no-skill'], $project->root());
    $initPayload = CliRunner::decode($init['stdout']);
    $initSummary = initValidateObject($initPayload, 'summary');
    $document = $project->readJson('sift.json');
    $validate = CliRunner::run(['validate'], $project->root());
    $validatePayload = CliRunner::decode($validate['stdout']);
    $validateSummary = initValidateObject($validatePayload, 'summary');

    expect($init['exit_code'])->toBe(0);
    expect($init['stderr'])->toBe('');
    expect($initSummary['config_path'] ?? null)->toBe($project->path('sift.json'));
    expect($initSummary['already_initialized'] ?? null)->toBeFalse();
    expect($initSummary['skill_installed'] ?? null)->toBeFalse();
    expect($document['$schema'])->toBe(ConfigDefaults::schemaUrl());
    expect($document['tools'])->toBe([]);
    expect($validate['exit_code'])->toBe(0);
    expect($validateSummary['using_defaults'] ?? null)->toBeFalse();
});

it('keeps init idempotent without force', function (): void {
    $project = FixtureProject::create();

    CliRunner::run(['init', '--no-skill'], $project->root());
    $again = CliRunner::run(['init', '--no-skill'], $project->root());
    $payload = CliRunner::decode($again['stdout']);
    $summary = initValidateObject($payload, 'summary');

    expect($again['exit_code'])->toBe(0);
    expect($summary['already_initialized'] ?? null)->toBeTrue();
});

it('supports command-level custom config path', function (): void {
    $project = FixtureProject::create();

    $init = CliRunner::run(['init', '--no-skill', '--config=custom/sift.json'], $project->root());
    $validate = CliRunner::run(['validate', '--config=custom/sift.json'], $project->root());
    $payload = CliRunner::decode($validate['stdout']);
    $summary = initValidateObject($payload, 'summary');

    expect($init['exit_code'])->toBe(0);
    expect($validate['exit_code'])->toBe(0);
    expect($project->path('custom/sift.json'))->toBeFile();
    expect($summary['config_path'] ?? null)->toBe($project->path('custom/sift.json'));
});

it('returns config errors as JSON on stderr', function (): void {
    $project = FixtureProject::create();
    $project->writeJson('sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'history' => [
            'max_files' => 0,
        ],
    ]);

    $result = CliRunner::run(['validate'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = initValidateObject($payload, 'error');

    expect($result['exit_code'])->toBe(3);
    expect($result['stdout'])->toBe('');
    expect($payload['status'] ?? null)->toBe('error');
    expect($error['code'] ?? null)->toBe('invalid_config');
});
