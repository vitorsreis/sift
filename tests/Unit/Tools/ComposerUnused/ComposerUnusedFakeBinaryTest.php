<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;
use Sift\Registry\ToolRegistry;
use Sift\Safety\MachineOutputPolicy;
use Sift\Safety\PolicyPipeline;
use Sift\Tools\CliArguments;
use Sift\Tools\ComposerUnused\ComposerUnusedToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs composer-unused through a fake binary with json output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'composer-unused',
        stdout: composerUnusedFakeBinaryJson(),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new ComposerUnusedToolAdapter()),
        policyPipeline: new PolicyPipeline([new MachineOutputPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('composer-unused'),
        config: composerUnusedFakeBinaryConfig(new ToolConfig('composer-unused', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('composer-unused');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['unused_packages' => 1]);
    expect($fake->argv())->toBe(['--output-format=json', '--no-progress', '--no-ansi', '--no-interaction']);
});

function composerUnusedFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
{
    $indexedTools = [];

    foreach ($tools as $tool) {
        $indexedTools[$tool->name()] = $tool;
    }

    return new SiftConfig(
        schema: 'https://raw.githubusercontent.com/vitorsreis/sift/master/resources/schema.json',
        configPath: null,
        usingDefaults: true,
        history: new HistoryConfig(false, '.sift/history', 50, 30, 1048576, true),
        output: new OutputConfig('compact', false, false),
        tools: $indexedTools,
    );
}

function composerUnusedFakeBinaryJson(): string
{
    return json_encode([
        'used-packages' => [['name' => 'php']],
        'unused-packages' => ['vimeo/psalm'],
        'ignored-packages' => ['composer-plugin-api'],
    ], JSON_THROW_ON_ERROR);
}
