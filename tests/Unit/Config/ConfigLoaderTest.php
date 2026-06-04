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
    expect($config->output()->format())->toBe('terminal');
    expect($config->output()->size())->toBe('compact');
    expect($config->output()->pretty())->toBeTrue();
    expect($config->output()->showProcess())->toBeFalse();
    expect($config->tool('*')?->enabled())->toBeTrue();
    expect((new ToolConfigResolver())->resolve($config, 'pest')->enabled())->toBeTrue();
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
    expect($config->history()->maxAgeDays())->toBeNull();
    expect($phpstan->enabled())->toBeTrue();
    expect($phpstan->binary())->toBe($project->path('config/vendor/bin/phpstan'));
    expect($phpstan->blockedArgs())->toBe(['--xdebug']);
    expect($phpstan->timeout())->toBe(0);
    expect($pest->enabled())->toBeTrue();
    expect($pest->binary())->toBeNull();
    expect($pest->timeout())->toBe(120);
});

it('loads output format from config', function (): void {
    $project = FixtureProject::create();
    $project->writeJson('sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'output' => [
            'format' => 'json',
        ],
    ]);

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))->resolve($project->root());
    $config = (new ConfigLoader())->load($workspace);

    expect($config->output()->format())->toBe('json');
});

it('rejects invalid output format values', function (): void {
    $project = FixtureProject::create();
    $project->writeJson('sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'output' => [
            'format' => 'xml',
        ],
    ]);

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))->resolve($project->root());

    expect(fn(): mixed => (new ConfigLoader())->load($workspace))
        ->toThrow(ConfigValidationException::class, 'The `output.format` value must be `terminal` or `json`.');
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

it('loads config without blocking on schema references', function (?string $schema): void {
    $project = FixtureProject::create();
    $document = [
        'tools' => [
            '*' => [
                'enabled' => true,
            ],
        ],
    ];

    if ($schema !== null) {
        $document['$schema'] = $schema;
    }

    $project->writeJson('sift.json', $document);

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))->resolve($project->root());
    $config = (new ConfigLoader())->load($workspace);

    expect($config->schema())->toBe(ConfigDefaults::schemaUrl());
    expect((new ToolConfigResolver())->resolve($config, 'pest')->enabled())->toBeTrue();
})->with([
    'resources/schema.json',
    'https://example.com/future-schema.json',
    null,
]);

it('ignores non string schema metadata while parsing config', function (): void {
    $project = FixtureProject::create();
    $project->writeJson('sift.json', ['$schema' => 2]);

    $workspace = (new WorkspaceResolver(homeDirectory: $project->path('home')))->resolve($project->root());
    $config = (new ConfigLoader())->load($workspace);

    expect($config->schema())->toBe(ConfigDefaults::schemaUrl());
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
