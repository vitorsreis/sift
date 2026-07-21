<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;
use Sift\Registry\ToolRegistry;
use Sift\Tools\CliArguments;
use Sift\Tools\Psalm\PsalmToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs psalm through a fake binary with injected json output', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'psalm',
        stdout: psalmFakeBinaryJson($source),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new PsalmToolAdapter()),
    );

    $result = $runner->run(
        arguments: new CliArguments('psalm', ['src']),
        config: psalmFakeBinaryConfig(new ToolConfig('psalm', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('psalm');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['issues' => 1, 'errors' => 1]);
    expect($fake->argv())->toBe(['--output-format=json', '--no-progress', '--monochrome', 'src']);
});

function psalmFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function psalmFakeBinaryJson(string $source): string
{
    return json_encode([
        [
            'severity' => 'error',
            'type' => 'UndefinedVariable',
            'message' => 'Cannot find referenced variable $total',
            'file_path' => $source,
            'line_from' => 12,
            'column_from' => 9,
        ],
    ], JSON_THROW_ON_ERROR);
}
