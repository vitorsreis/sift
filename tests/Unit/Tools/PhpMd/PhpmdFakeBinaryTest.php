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
use Sift\Tools\PhpMd\PhpmdToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs phpmd through a fake binary with json output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'phpmd',
        stdout: phpmdFakeBinaryJson(),
        exitCode: 2,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new PhpmdToolAdapter()),
        policyPipeline: new PolicyPipeline([new MachineOutputPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('phpmd', ['src']),
        config: phpmdFakeBinaryConfig(new ToolConfig('phpmd', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('phpmd');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['violations' => 1]);
    expect($fake->argv())->toBe(['src', 'json', 'cleancode,codesize,controversial,design,naming,unusedcode']);
});

function phpmdFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
{
    $indexedTools = [];

    foreach ($tools as $tool) {
        $indexedTools[$tool->name()] = $tool;
    }

    return new SiftConfig(
        schema: 'https://raw.githubusercontent.com/vitorsreis/sift/v2.0.0/resources/schema.json',
        configPath: null,
        usingDefaults: true,
        history: new HistoryConfig(false, '.sift/history', 50, 30, 1048576, true),
        output: new OutputConfig('compact', false, false),
        tools: $indexedTools,
    );
}

function phpmdFakeBinaryJson(): string
{
    return json_encode([
        'version' => '2.15.0',
        'package' => 'PHPMD',
        'files' => [
            [
                'file' => 'src/Checkout.php',
                'violations' => [
                    [
                        'beginLine' => 12,
                        'endLine' => 12,
                        'package' => null,
                        'class' => null,
                        'method' => null,
                        'description' => 'Avoid using static access.',
                        'rule' => 'StaticAccess',
                        'ruleSet' => 'Clean Code Rules',
                        'externalInfoUrl' => 'https://phpmd.org/rules/cleancode.html#staticaccess',
                        'priority' => 1,
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
