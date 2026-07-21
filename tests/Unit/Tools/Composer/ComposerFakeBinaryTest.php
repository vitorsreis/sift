<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;
use Sift\Registry\ToolRegistry;
use Sift\Safety\ComposerReadOnlyPolicy;
use Sift\Safety\MachineOutputPolicy;
use Sift\Safety\PolicyPipeline;
use Sift\Tools\CliArguments;
use Sift\Tools\Composer\ComposerToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs composer audit through a fake binary with json output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'composer',
        stdout: composerFakeBinaryAuditJson(),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new ComposerToolAdapter()),
        policyPipeline: new PolicyPipeline([new ComposerReadOnlyPolicy(), new MachineOutputPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('composer', ['audit']),
        config: composerFakeBinaryConfig(new ToolConfig('composer', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('composer');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['abandoned_packages' => 1]);
    expect($fake->argv())->toBe(['audit', '--no-ansi', '--no-interaction', '--format=json']);
});

function composerFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function composerFakeBinaryAuditJson(): string
{
    return json_encode([
        'advisories' => [],
        'abandoned' => [
            'azjezz/psl' => 'php-standard-library/php-standard-library',
        ],
    ], JSON_THROW_ON_ERROR);
}
