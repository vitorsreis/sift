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
use Sift\Tools\ParallelLint\ParallelLintToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs parallel-lint through a fake binary with json output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'parallel-lint',
        stdout: parallelLintFakeBinaryJson(),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new ParallelLintToolAdapter()),
        policyPipeline: new PolicyPipeline([new MachineOutputPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('parallel-lint'),
        config: parallelLintFakeBinaryConfig(new ToolConfig('parallel-lint', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('parallel-lint');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['errors' => 1]);
    expect($fake->argv())->toBe(['.', '--json', '--no-progress', '--no-colors']);
});

function parallelLintFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function parallelLintFakeBinaryJson(): string
{
    return json_encode([
        'phpVersion' => 80506,
        'hhvmVersion' => '',
        'parallelJobs' => 10,
        'results' => [
            'checkedFiles' => [],
            'filesWithSyntaxError' => ['src/Broken.php'],
            'skippedFiles' => [],
            'errors' => [
                [
                    'type' => 'syntaxError',
                    'file' => 'src/Broken.php',
                    'line' => 1,
                    'message' => 'Parse error: syntax error, unexpected token ";" in Broken.php on line 1',
                    'normalizeMessage' => 'Unexpected token ";".',
                    'blame' => null,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
