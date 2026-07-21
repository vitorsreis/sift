<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;
use Sift\Registry\ToolRegistry;
use Sift\Safety\PolicyPipeline;
use Sift\Safety\RectorDryRunPolicy;
use Sift\Tools\CliArguments;
use Sift\Tools\Rector\RectorToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs rector through a fake binary with dry-run json output', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'rector',
        stdout: rectorFakeBinaryJson($source),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new RectorToolAdapter()),
        policyPipeline: new PolicyPipeline([new RectorDryRunPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('rector', ['src']),
        config: rectorFakeBinaryConfig(new ToolConfig('rector', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('rector');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['changed_files' => 1, 'errors' => 0]);
    expect($fake->argv())->toBe(['process', '--dry-run', '--output-format=json', '--no-progress-bar', '--no-ansi', 'src']);
});

function rectorFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function rectorFakeBinaryJson(string $source): string
{
    return json_encode([
        'totals' => [
            'changed_files' => 1,
            'errors' => 0,
        ],
        'changed_files' => [$source],
        'file_diffs' => [
            [
                'file' => $source,
                'diff' => "--- Original\n+++ New",
                'applied_rectors' => ['Rector\\ExampleRector'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
