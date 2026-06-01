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
use Sift\Safety\MagoSafeModePolicy;
use Sift\Safety\PolicyPipeline;
use Sift\Tools\CliArguments;
use Sift\Tools\Mago\MagoToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs mago through a fake binary with json issue output', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'mago',
        stdout: magoFakeBinaryIssueJson($source),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new MagoToolAdapter()),
        policyPipeline: new PolicyPipeline([new MagoSafeModePolicy(), new MachineOutputPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('mago', ['src']),
        config: magoFakeBinaryConfig(new ToolConfig('mago', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('mago');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['issues' => 1]);
    expect($fake->argv())->toBe(['--colors=never', 'lint', '--reporting-format=json', 'src']);
});

function magoFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function magoFakeBinaryIssueJson(string $source): string
{
    return json_encode([
        'issues' => [
            [
                'level' => 'error',
                'code' => 'lint:no-empty',
                'message' => 'Empty block detected.',
                'annotations' => [
                    [
                        'kind' => 'primary',
                        'span' => [
                            'file_id' => [
                                'name' => $source,
                                'path' => $source,
                                'size' => 42,
                                'file_type' => 'host',
                            ],
                            'start' => ['line' => 7, 'offset' => 10],
                            'end' => ['line' => 7, 'offset' => 12],
                        ],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
