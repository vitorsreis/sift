<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;

it('exposes config values and filters wildcard tools from listed tools', function (): void {
    $history = new HistoryConfig(true, '.sift/history', 10, null, 1024, true, true);
    $output = new OutputConfig('compact', true, false, 'json');
    $phpstan = new ToolConfig('phpstan', true, 'vendor/bin/phpstan', ['--generate-baseline'], 60);
    $wildcard = new ToolConfig('*', true, null, [], 30);
    $config = new SiftConfig(
        schema: 'schema.json',
        configPath: '/project/sift.json',
        usingDefaults: false,
        history: $history,
        output: $output,
        tools: [
            '*' => $wildcard,
            'phpstan' => $phpstan,
        ],
    );

    expect($config->schema())->toBe('schema.json');
    expect($config->configPath())->toBe('/project/sift.json');
    expect($config->usingDefaults())->toBeFalse();
    expect($config->history())->toBe($history);
    expect($config->output())->toBe($output);
    expect($config->tools())->toBe(['phpstan' => $phpstan]);
    expect($config->tool('*'))->toBe($wildcard);
    expect($config->tool('phpstan'))->toBe($phpstan);
    expect($config->tool('pest'))->toBeNull();
});

it('exposes history output and tool config values', function (): void {
    $history = new HistoryConfig(false, '/history', 5, 30, 2048, false, false);
    $output = new OutputConfig('full', false, true, 'terminal');
    $tool = new ToolConfig('pest', false, 'vendor/bin/pest', ['--update-snapshots'], 120);

    expect($history->enabled())->toBeFalse();
    expect($history->path())->toBe('/history');
    expect($history->maxFiles())->toBe(5);
    expect($history->maxAgeDays())->toBe(30);
    expect($history->maxBytesPerRun())->toBe(2048);
    expect($history->redactSecrets())->toBeFalse();
    expect($history->defaultPath())->toBeFalse();

    expect($output->size())->toBe('full');
    expect($output->pretty())->toBeFalse();
    expect($output->showProcess())->toBeTrue();
    expect($output->format())->toBe('terminal');

    expect($tool->name())->toBe('pest');
    expect($tool->enabled())->toBeFalse();
    expect($tool->binary())->toBe('vendor/bin/pest');
    expect($tool->blockedArgs())->toBe(['--update-snapshots']);
    expect($tool->timeout())->toBe(120);
});
