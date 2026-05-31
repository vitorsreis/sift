<?php

declare(strict_types=1);

use Sift\Config\ConfigDefaults;
use Sift\Config\ConfigLoader;
use Sift\Config\ConfigValidationException;
use Sift\Config\ToolConfigResolver;
use Sift\Workspace\WorkspaceResolver;
use Tests\Support\FixtureProject;

it('loads defaults when config is absent', function (): void {
    $project = FixtureProject::create();
    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))->resolve($project->root());

    $config = (new ConfigLoader())->load($workspace);

    expect($config->usingDefaults())->toBeTrue();
    expect($config->configPath())->toBeNull();
    expect($config->schema())->toBe(ConfigDefaults::schemaUrl());
    expect($config->history()->enabled())->toBeTrue();
    expect($config->history()->path())->toBe($project->path('.sift/history'));
    expect($config->output()->size())->toBe('compact');
    expect($config->output()->pretty())->toBeTrue();
    expect($config->output()->showProcess())->toBeTrue();
    expect($config->tools())->toBe([]);
});

it('loads partial supported config and resolves config-relative paths', function (): void {
    $project = FixtureProject::create();
    $configPath = $project->writeJson('config/sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'history' => [
            'path' => 'storage/sift-history',
        ],
        'tools' => [
            '*' => [
                'enabled' => true,
                'timeout' => 120,
            ],
            'phpstan' => [
                'binary' => 'vendor/bin/phpstan',
                'blocked_args' => ['--xdebug'],
                'timeout' => 0,
            ],
        ],
    ]);

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))->resolve($project->root(), $configPath);
    $config = (new ConfigLoader())->load($workspace);
    $resolver = new ToolConfigResolver();
    $phpstan = $resolver->resolve($config, 'phpstan');
    $pest = $resolver->resolve($config, 'pest');

    expect($config->usingDefaults())->toBeFalse();
    expect($config->configPath())->toBe($configPath);
    expect($config->history()->path())->toBe($project->path('config/storage/sift-history'));
    expect($phpstan->enabled())->toBeTrue();
    expect($phpstan->binary())->toBe($project->path('config/vendor/bin/phpstan'));
    expect($phpstan->blockedArgs())->toBe(['--xdebug']);
    expect($phpstan->timeout())->toBe(0);
    expect($pest->enabled())->toBeTrue();
    expect($pest->binary())->toBeNull();
    expect($pest->timeout())->toBe(120);
});

it('keeps path binaries as command names', function (): void {
    $project = FixtureProject::create();
    $project->writeJson('sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'tools' => [
            'phpstan' => [
                'binary' => 'phpstan',
            ],
        ],
    ]);

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))->resolve($project->root());
    $config = (new ConfigLoader())->load($workspace);

    expect((new ToolConfigResolver())->resolve($config, 'phpstan')->binary())->toBe('phpstan');
});

it('rejects missing or unsupported schema as schema contract errors', function (): void {
    $project = FixtureProject::create();
    $project->writeJson('missing-schema.json', ['tools' => []]);
    $project->writeJson('bad-schema.json', ['$schema' => 'https://example.com/schema.json']);

    $resolver = new WorkspaceResolver(homeDirectory: $project->path('home'));
    $loader = new ConfigLoader();

    expect(fn(): mixed => $loader->load($resolver->resolve($project->root(), $project->path('missing-schema.json'))))
        ->toThrow(ConfigValidationException::class, 'The `$schema` field is required.');

    expect(fn(): mixed => $loader->load($resolver->resolve($project->root(), $project->path('bad-schema.json'))))
        ->toThrow(ConfigValidationException::class, 'Unsupported config schema.');
});

it('rejects invalid semantic values without coercing arrays', function (): void {
    $project = FixtureProject::create();
    $project->writeJson('sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'tools' => [
            'phpstan' => [
                'blocked_args' => ['--memory-limit=1G', 123],
            ],
        ],
    ]);

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))->resolve($project->root());

    expect(fn(): mixed => (new ConfigLoader())->load($workspace))
        ->toThrow(ConfigValidationException::class, 'The `tools.phpstan.blocked_args` entries must be strings.');
});
