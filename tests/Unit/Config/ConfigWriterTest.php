<?php

declare(strict_types=1);

use Sift\Config\ConfigDefaults;
use Sift\Config\ConfigWriter;
use Tests\Support\FixtureProject;

it('writes the minimal default config document', function (): void {
    $project = FixtureProject::create();
    $path = $project->path('sift.json');

    (new ConfigWriter())->writeDefaults($path);

    $document = $project->readJson('sift.json');

    expect($document)->toBe([
        '$schema' => ConfigDefaults::schemaUrl(),
        'output' => [
            'format' => 'terminal',
            'size' => 'compact',
            'pretty' => true,
            'show_process' => false,
        ],
        'history' => [
            'enabled' => true,
            'path' => '.sift/history',
            'max_files' => 50,
            'max_age_days' => 30,
            'max_bytes_per_run' => 1048576,
            'redact_secrets' => true,
        ],
        'tools' => [
            '*' => [
                'enabled' => true,
            ],
        ],
    ]);
    expect((string) file_get_contents($path))->toContain('"*"');
    expect((string) file_get_contents($path))->toContain('"enabled": true');
    expect(glob($project->path('sift.json.tmp.*')))->toBe([]);
});

it('preserves known overrides when rewriting defaults', function (): void {
    $project = FixtureProject::create();
    $path = $project->writeJson('sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'output' => [
            'format' => 'json',
            'size' => 'full',
            'pretty' => false,
        ],
        'history' => [
            'enabled' => false,
            'path' => 'tmp/history',
        ],
        'tools' => [
            'phpstan' => [
                'binary' => 'vendor/bin/phpstan',
                'blocked_args' => ['--xdebug'],
                'timeout' => 0,
            ],
        ],
    ]);

    (new ConfigWriter())->writeDefaults($path, [
        '$schema' => ConfigDefaults::schemaUrl(),
        'output' => [
            'format' => 'json',
            'size' => 'full',
            'pretty' => false,
        ],
        'history' => [
            'enabled' => false,
            'path' => 'tmp/history',
        ],
        'tools' => [
            'phpstan' => [
                'binary' => 'vendor/bin/phpstan',
                'blocked_args' => ['--xdebug'],
                'timeout' => 0,
            ],
        ],
    ]);

    $document = $project->readJson('sift.json');

    expect($document['$schema'])->toBe(ConfigDefaults::schemaUrl());
    expect($document['output'])->toMatchArray([
        'format' => 'json',
        'size' => 'full',
        'pretty' => false,
        'show_process' => false,
    ]);
    expect($document['history'])->toMatchArray([
        'enabled' => false,
        'path' => 'tmp/history',
        'max_files' => 50,
    ]);
    expect($document['tools'])->toBe([
        'phpstan' => [
            'binary' => 'vendor/bin/phpstan',
            'blocked_args' => ['--xdebug'],
            'timeout' => 0,
        ],
    ]);
});
