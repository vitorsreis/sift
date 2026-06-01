<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;
use Sift\Filesystem\TempFileFactory;
use Sift\Registry\ToolRegistry;
use Sift\Safety\PolicyPipeline;
use Sift\Tools\CliArguments;
use Sift\Tools\Infection\InfectionToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs infection through a fake binary with generated summary json output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'infection',
        exitCode: 1,
        writes: ['--logger-summary-json' => infectionFakeBinaryJson()],
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new InfectionToolAdapter(tempFileFactory: new TempFileFactory($project->path('build/tmp')))),
        policyPipeline: new PolicyPipeline([]),
    );

    $result = $runner->run(
        arguments: new CliArguments('infection', ['--min-msi=80']),
        config: infectionFakeBinaryConfig(new ToolConfig('infection', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();
    $argv = $fake->argv();

    expect($payload['tool'])->toBe('infection');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['total_mutants' => 4, 'msi' => 75.0]);
    expect($argv[0])->toBe('--logger-summary-json');
    expect($argv[2])->toBe('--min-msi=80');
});

function infectionFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function infectionFakeBinaryJson(): string
{
    return json_encode([
        'stats' => [
            'totalMutantsCount' => 4,
            'killedCount' => 3,
            'killedByStaticAnalysisCount' => 0,
            'notCoveredCount' => 0,
            'escapedCount' => 1,
            'errorCount' => 0,
            'syntaxErrorCount' => 0,
            'skippedCount' => 0,
            'ignoredCount' => 0,
            'timeOutCount' => 0,
            'msi' => 75.0,
            'mutationCodeCoverage' => 100.0,
            'coveredCodeMsi' => 75.0,
        ],
    ], JSON_THROW_ON_ERROR);
}
