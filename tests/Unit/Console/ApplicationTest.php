<?php

declare(strict_types=1);

use Sift\Console\Application;
use Tests\Support\FixtureProject;

it('renders help to stdout when no command is provided', function (): void {
    $stdout = '';
    $stderr = '';
    $project = FixtureProject::create();
    $application = new Application(
        stdoutWriter: static function (string $contents) use (&$stdout): void {
            $stdout .= $contents;
        },
        stderrWriter: static function (string $contents) use (&$stderr): void {
            $stderr .= $contents;
        },
        cwd: $project->root(),
    );

    $exitCode = $application->run(['sift']);

    expect($exitCode)->toBe(0);
    expect($stdout)->toContain('Sift');
    expect($stdout)->toContain('Commands');
    expect($stderr)->toBe('');
});

it('renders invalid usage as json on stderr when json is requested', function (): void {
    $stdout = '';
    $stderr = '';
    $project = FixtureProject::create();
    $application = new Application(
        stdoutWriter: static function (string $contents) use (&$stdout): void {
            $stdout .= $contents;
        },
        stderrWriter: static function (string $contents) use (&$stderr): void {
            $stderr .= $contents;
        },
        cwd: $project->root(),
    );

    $exitCode = $application->run(['sift', '--json', '--compact', '--full', 'help']);
    $payload = applicationTestPayload($stderr);
    $error = applicationTestPayloadValue($payload, 'error');

    expect($exitCode)->toBe(3);
    expect($stdout)->toBe('');
    expect($payload['status'] ?? null)->toBe('error');
    expect($error['code'] ?? null)->toBe('invalid_usage');
    expect($error['message'] ?? null)->toBe('Options "--compact" and "--full" cannot be used together.');
});

it('writes debug output to stderr with sensitive options redacted', function (): void {
    $stdout = '';
    $stderr = '';
    $project = FixtureProject::create();
    $application = new Application(
        stdoutWriter: static function (string $contents) use (&$stdout): void {
            $stdout .= $contents;
        },
        stderrWriter: static function (string $contents) use (&$stderr): void {
            $stderr .= $contents;
        },
        cwd: $project->root(),
    );

    $exitCode = $application->run(['sift', '--debug', '--no-pretty', '--config=token-secret-123', 'help']);
    $payload = applicationTestPayload(trim($stderr));
    $globalOptions = applicationTestPayloadValue($payload, 'global_options');

    expect($exitCode)->toBe(0);
    expect($stdout)->toContain('Sift');
    expect($payload['type'] ?? null)->toBe('debug');
    expect($globalOptions['config'] ?? null)->toBe('[REDACTED]');
});

/**
 * @return array<string, mixed>
 */
function applicationTestPayload(string $json): array
{
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload) || array_is_list($payload)) {
        throw new RuntimeException('Expected object payload.');
    }

    $normalized = [];

    foreach ($payload as $key => $value) {
        if (! is_string($key)) {
            throw new RuntimeException('Expected string payload keys.');
        }

        $normalized[$key] = $value;
    }

    return $normalized;
}

/**
 * @param array<string, mixed> $payload
 *
 * @return array<string, mixed>
 */
function applicationTestPayloadValue(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value) || array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected "%s" object payload value.', $key));
    }

    $normalized = [];

    foreach ($value as $nestedKey => $nestedValue) {
        if (! is_string($nestedKey)) {
            throw new RuntimeException(sprintf('Expected "%s" object string keys.', $key));
        }

        $normalized[$nestedKey] = $nestedValue;
    }

    return $normalized;
}
