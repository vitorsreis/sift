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
use Sift\Tools\Deptrac\DeptracToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs deptrac through a fake binary with json analyse output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'deptrac',
        stdout: deptracFakeBinaryJson(),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new DeptracToolAdapter()),
        policyPipeline: new PolicyPipeline([new MachineOutputPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('deptrac'),
        config: deptracFakeBinaryConfig(new ToolConfig('deptrac', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('deptrac');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['violations' => 1]);
    expect($fake->argv())->toBe(['--formatter=json', '--no-progress', '--report-skipped']);
});

function deptracFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function deptracFakeBinaryJson(): string
{
    return json_encode([
        'Report' => [
            'Violations' => 1,
            'Skipped violations' => 0,
            'Uncovered' => 0,
            'Allowed' => 4,
            'Warnings' => 0,
            'Errors' => 0,
        ],
        'files' => [
            'src/Application/Checkout.php' => [
                'messages' => [
                    [
                        'message' => 'App\\Controller\\CheckoutController must not depend on App\\Infrastructure\\PaymentGateway (Controller on Infrastructure)',
                        'line' => 12,
                        'type' => 'error',
                    ],
                ],
                'violations' => 1,
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
