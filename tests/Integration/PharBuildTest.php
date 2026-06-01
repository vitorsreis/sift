<?php

declare(strict_types=1);

use Tests\Support\FixtureProject;

it('runs the phar from an isolated directory', function (): void {
    $root = dirname(__DIR__, 2);
    $build = runPharEntrypointProcess([PHP_BINARY, '-d', 'phar.readonly=0', $root . '/bin/phar'], $root);

    if ($build['exit_code'] !== 0) {
        throw new RuntimeException($build['stderr'] . PHP_EOL . $build['stdout']);
    }

    $project = FixtureProject::create('sift-phar-entrypoint-');
    $sourcePhar = $root . '/build/phar/sift.phar';
    $targetPhar = $project->path('sift.phar');

    if (! copy($sourcePhar, $targetPhar)) {
        throw new RuntimeException('Could not copy PHAR to isolated fixture.');
    }

    $source = runPharEntrypointProcess([PHP_BINARY, $root . '/bin/sift', '--no-pretty', 'help'], $project->root());
    $result = runPharEntrypointProcess([PHP_BINARY, $targetPhar, '--no-pretty', 'help'], $project->root());
    $payload = decodePharEntrypointPayload($result['stdout']);

    expect($source['exit_code'])->toBe(0);
    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->toBe($source['stdout']);
    expect($payload['tool'] ?? null)->toBe('sift');
    expect($payload['status'] ?? null)->toBe('passed');
    expect($payload['command'] ?? null)->toBe('help');
});

/**
 * @param list<string> $command
 *
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runPharEntrypointProcess(array $command, string $cwd): array
{
    $process = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $cwd,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not start PHAR process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Could not read PHAR process output.');
    }

    return [
        'exit_code' => proc_close($process),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * @return array<string, mixed>
 */
function decodePharEntrypointPayload(string $json): array
{
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload) || array_is_list($payload)) {
        throw new RuntimeException('PHAR payload must be an object.');
    }

    $normalized = [];

    foreach ($payload as $key => $value) {
        if (! is_string($key)) {
            throw new RuntimeException('PHAR payload must use string keys.');
        }

        $normalized[$key] = $value;
    }

    return $normalized;
}
