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
use Sift\Tools\Pint\PintToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs pint through a fake binary with json output', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'pint',
        stdout: pintFakeBinaryJson($source),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new PintToolAdapter()),
        policyPipeline: new PolicyPipeline([new MachineOutputPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('pint', ['src']),
        config: pintFakeBinaryConfig(new ToolConfig('pint', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('pint');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['result' => 'fail', 'files' => 1]);
    expect($fake->argv())->toBe(['--test', '--format=json', 'src']);
});

function pintFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function pintFakeBinaryJson(string $source): string
{
    return json_encode([
        'about' => 'PHP CS Fixer 3.75.0',
        'files' => [
            [
                'path' => $source,
                'fixers' => ['ordered_imports'],
            ],
        ],
        'time' => [
            'total' => 0.123,
        ],
        'memory' => 12.345,
    ], JSON_THROW_ON_ERROR);
}
