<?php

declare(strict_types=1);

use Tests\Support\FixtureProject;

it('runs composer sift and composer skills from an installed plugin package', function (): void {
    $project = FixtureProject::create('sift-composer-entrypoint-');
    $repoRoot = str_replace('\\', '/', dirname(__DIR__, 2));

    $project->writeJson('composer.json', [
        'name' => 'fixture/project',
        'type' => 'project',
        'require' => [
            'vitorsreis/sift' => '*',
        ],
        'repositories' => [
            [
                'type' => 'path',
                'url' => $repoRoot,
                'options' => [
                    'symlink' => true,
                ],
            ],
        ],
        'config' => [
            'allow-plugins' => [
                'vitorsreis/sift' => true,
            ],
            'platform' => [
                'php' => '8.3',
            ],
        ],
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
    ]);
    $composerJson = (string) file_get_contents($project->path('composer.json'));

    $install = runComposerEntrypoint($project, ['install', '--no-interaction', '--no-progress', '--no-ansi']);

    if ($install['exit_code'] !== 0) {
        throw new RuntimeException($install['stderr'] . PHP_EOL . $install['stdout']);
    }

    expect((string) file_get_contents($project->path('composer.json')))->toBe($composerJson);

    $sift = runComposerEntrypoint($project, ['sift', '--no-pretty', 'help']);
    $skills = runComposerEntrypoint($project, ['skills', '--no-pretty', 'list']);
    $vendorBin = runVendorSiftEntrypoint($project, ['--no-pretty', 'help']);

    expect((string) file_get_contents($project->path('composer.json')))->toBe($composerJson);

    expect($sift['exit_code'])->toBe(0);
    expect($sift['stderr'])->toBe('');
    expect(decodeComposerEntrypointPayload($sift['stdout'])['command'] ?? null)->toBe('help');

    expect($skills['exit_code'])->toBe(0);
    expect($skills['stderr'])->toBe('');
    expect(decodeComposerEntrypointPayload($skills['stdout'])['total'] ?? null)->toBe(0);

    expect($vendorBin['exit_code'])->toBe(0);
    expect($vendorBin['stderr'])->toBe('');
    expect($vendorBin['stdout'])->toBe($sift['stdout']);
});

/**
 * @param list<string> $arguments
 *
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runComposerEntrypoint(FixtureProject $project, array $arguments): array
{
    $root = dirname(__DIR__, 2);
    $command = [PHP_BINARY, $root . '/vendor/bin/composer', '--working-dir', $project->root()];

    foreach ($arguments as $argument) {
        $command[] = $argument;
    }

    $process = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not start Composer process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Could not read Composer process output.');
    }

    return [
        'exit_code' => proc_close($process),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * @param list<string> $arguments
 *
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runVendorSiftEntrypoint(FixtureProject $project, array $arguments): array
{
    $command = [PHP_BINARY, $project->path('vendor/bin/sift')];

    foreach ($arguments as $argument) {
        $command[] = $argument;
    }

    $process = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $project->root(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not start vendor/bin/sift process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Could not read vendor/bin/sift process output.');
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
function decodeComposerEntrypointPayload(string $json): array
{
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload) || array_is_list($payload)) {
        throw new RuntimeException('Composer entrypoint payload must be an object.');
    }

    $normalized = [];

    foreach ($payload as $key => $value) {
        if (! is_string($key)) {
            throw new RuntimeException('Composer entrypoint payload must use string keys.');
        }

        $normalized[$key] = $value;
    }

    return $normalized;
}
