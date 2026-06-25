<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;
use Sift\Execution\ProcessSupervisor;
use Tests\Support\FixtureProject;

it('runs composer sift and composer skills from an installed plugin package', function (): void {
    $project = FixtureProject::create('sift-composer-entrypoint-');
    $codexHome = FixtureProject::create('sift-codex-home-');
    $previousCodexHome = getenv('CODEX_HOME');
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

    putenv('CODEX_HOME=' . $codexHome->root());

    try {
        $install = runComposerEntrypoint($project, ['install', '--no-interaction', '--no-progress', '--no-ansi']);

        if ($install['exit_code'] !== 0) {
            throw new RuntimeException($install['stderr'] . PHP_EOL . $install['stdout']);
        }

        expect((string) file_get_contents($project->path('composer.json')))->toBe($composerJson);

        $sift = runComposerEntrypoint($project, ['sift', '--json', '--no-pretty', 'help']);
        $skills = runComposerEntrypoint($project, ['skills', '--json', '--no-pretty', 'list']);
        $vendorBin = runVendorSiftEntrypoint($project, ['--json', '--no-pretty', 'help']);
    } finally {
        putenv($previousCodexHome === false ? 'CODEX_HOME' : 'CODEX_HOME=' . $previousCodexHome);
    }

    expect((string) file_get_contents($project->path('composer.json')))->toBe($composerJson);

    expect($sift['exit_code'])->toBe(0);
    expect($sift['stderr'])->toBe('');
    expect($sift['stdout'])->toContain('Sift');
    expect($sift['stdout'])->toContain('Commands');

    expect($skills['exit_code'])->toBe(0);
    expect($skills['stderr'])->toBe('');
    expect(decodeComposerEntrypointPayload($skills['stdout'])['total'] ?? null)->toBe(0);

    expect($vendorBin['exit_code'])->toBe(0);
    expect($vendorBin['stderr'])->toBe('');
    expect($vendorBin['stdout'])->toBe($sift['stdout']);
});

it('installs the composer package as a copied distribution', function (): void {
    $project = FixtureProject::create('sift-composer-dist-');
    $package = FixtureProject::create('sift-package-export-');
    $repoRoot = exportCurrentPackage($package->root());

    $project->writeJson('composer.json', [
        'name' => 'fixture/dist-project',
        'type' => 'project',
        'require' => [
            'vitorsreis/sift' => '*',
        ],
        'repositories' => [
            [
                'type' => 'path',
                'url' => $repoRoot,
                'options' => [
                    'symlink' => false,
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

    $install = runComposerEntrypoint($project, ['install', '--no-interaction', '--no-progress', '--no-ansi']);
    $validatePackage = runComposerEntrypointIn($project->path('vendor/vitorsreis/sift'), ['validate', '--strict', '--no-ansi']);
    $vendorBin = runVendorSiftEntrypoint($project, ['version']);
    $vendorValidate = runVendorSiftEntrypoint($project, ['--json', '--no-pretty', 'validate']);

    if ($install['exit_code'] !== 0) {
        throw new RuntimeException($install['stderr'] . PHP_EOL . $install['stdout']);
    }

    if ($vendorBin['exit_code'] !== 0) {
        throw new RuntimeException($vendorBin['stderr'] . PHP_EOL . $vendorBin['stdout']);
    }

    if ($vendorValidate['exit_code'] !== 0) {
        throw new RuntimeException($vendorValidate['stderr'] . PHP_EOL . $vendorValidate['stdout']);
    }

    expect($validatePackage['exit_code'])->toBe(0);
    expect($vendorBin['exit_code'])->toBe(0);
    expect($vendorValidate['exit_code'])->toBe(0);
    expect(stripSiftAnsi($vendorBin['stdout']))->toStartWith('Sift ');
    expect(decodeComposerEntrypointPayload($vendorValidate['stdout'])['status'] ?? null)->toBe('passed');
    expect($project->path('vendor/vitorsreis/sift/src/Sift.php'))->toBeFile();
});

it('runs vendor bin when the composer plugin is disabled', function (): void {
    $project = FixtureProject::create('sift-composer-plugin-disabled-');
    $repoRoot = str_replace('\\', '/', dirname(__DIR__, 2));

    $project->writeJson('composer.json', [
        'name' => 'fixture/plugin-disabled-project',
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
                'vitorsreis/sift' => false,
            ],
            'platform' => [
                'php' => '8.3',
            ],
        ],
        'minimum-stability' => 'dev',
        'prefer-stable' => true,
    ]);

    $install = runComposerEntrypoint($project, ['install', '--no-interaction', '--no-progress', '--no-ansi']);
    $vendorBin = runVendorSiftEntrypoint($project, ['--json', '--no-pretty', 'validate']);

    if ($install['exit_code'] !== 0) {
        throw new RuntimeException($install['stderr'] . PHP_EOL . $install['stdout']);
    }

    expect($vendorBin['exit_code'])->toBe(0);
    expect(decodeComposerEntrypointPayload($vendorBin['stdout'])['status'] ?? null)->toBe('passed');
});

/**
 * @param list<string> $arguments
 *
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runComposerEntrypoint(FixtureProject $project, array $arguments): array
{
    $root = dirname(__DIR__, 2);
    $command = new PreparedCommand(
        tool: 'composer',
        binary: PHP_BINARY,
        arguments: [$root . '/vendor/bin/composer', '--working-dir', $project->root(), ...$arguments],
        cwd: $root,
        timeout: 90,
    );
    $result = (new ProcessSupervisor())->run($command, timeoutSeconds: (float) $command->timeout());

    return [
        'exit_code' => $result->exitCode(),
        'stdout' => $result->stdout(),
        'stderr' => $result->timedOut()
            ? trim($result->stderr() . PHP_EOL . 'Composer process timed out after 90 seconds.')
            : $result->stderr(),
    ];
}

function exportCurrentPackage(string $targetRoot): string
{
    $sourceRoot = dirname(__DIR__, 2);

    foreach (['bin', 'src', 'resources', 'skills', 'docs'] as $directory) {
        copyPackageDirectory($sourceRoot . DIRECTORY_SEPARATOR . $directory, $targetRoot . DIRECTORY_SEPARATOR . $directory);
    }

    foreach ([
        'composer.json',
        'README.md',
        'CHANGELOG.md',
        'LICENSE.md',
        'CODE_OF_CONDUCT.md',
        'CONTRIBUTING.md',
        'SECURITY.md',
    ] as $file) {
        copy($sourceRoot . DIRECTORY_SEPARATOR . $file, $targetRoot . DIRECTORY_SEPARATOR . $file);
    }

    return str_replace('\\', '/', $targetRoot);
}

function copyPackageDirectory(string $source, string $target): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        if (! $item instanceof SplFileInfo) {
            continue;
        }

        $targetPath = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

        if ($item->isDir()) {
            if (! is_dir($targetPath) && ! mkdir($targetPath, 0777, true) && ! is_dir($targetPath)) {
                throw new RuntimeException(sprintf('Could not create package export directory "%s".', $targetPath));
            }

            continue;
        }

        $targetDirectory = dirname($targetPath);

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0777, true) && ! is_dir($targetDirectory)) {
            throw new RuntimeException(sprintf('Could not create package export directory "%s".', $targetDirectory));
        }

        copy($item->getPathname(), $targetPath);
    }
}

/**
 * @param list<string> $arguments
 *
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runComposerEntrypointIn(string $workingDirectory, array $arguments): array
{
    $root = dirname(__DIR__, 2);
    $command = new PreparedCommand(
        tool: 'composer',
        binary: PHP_BINARY,
        arguments: [$root . '/vendor/bin/composer', '--working-dir', $workingDirectory, ...$arguments],
        cwd: $root,
        timeout: 90,
    );
    $result = (new ProcessSupervisor())->run($command, timeoutSeconds: (float) $command->timeout());

    return [
        'exit_code' => $result->exitCode(),
        'stdout' => $result->stdout(),
        'stderr' => $result->timedOut()
            ? trim($result->stderr() . PHP_EOL . 'Composer process timed out after 90 seconds.')
            : $result->stderr(),
    ];
}

/**
 * @param list<string> $arguments
 *
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runVendorSiftEntrypoint(FixtureProject $project, array $arguments): array
{
    $command = new PreparedCommand(
        tool: 'sift',
        binary: PHP_BINARY,
        arguments: [$project->path('vendor/bin/sift'), ...$arguments],
        cwd: $project->root(),
        timeout: 30,
    );
    $result = (new ProcessSupervisor())->run($command, timeoutSeconds: (float) $command->timeout());

    return [
        'exit_code' => $result->exitCode(),
        'stdout' => $result->stdout(),
        'stderr' => $result->timedOut()
            ? trim($result->stderr() . PHP_EOL . 'Sift process timed out after 30 seconds.')
            : $result->stderr(),
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
