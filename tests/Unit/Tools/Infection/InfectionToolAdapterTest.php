<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\CliArguments;
use Sift\Tools\Infection\InfectionToolAdapter;
use Tests\Support\FixtureProject;

it('describes infection discovery metadata', function (): void {
    $definition = (new InfectionToolAdapter())->definition();

    expect($definition->name())->toBe('infection');
    expect($definition->description())->toBe('Infection mutation testing.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/infection.bat', 'vendor/bin/infection', 'infection']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev infection/infection');
    expect($definition->defaultContext())->toBe('mutation');
});

it('prepares infection with generated summary json output', function (): void {
    $project = FixtureProject::create();
    $adapter = infectionAdapter($project);
    $context = $adapter->context(new CliArguments('infection', ['--min-msi=80']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('infection', $project->path('vendor/bin/infection'), 'vendor/bin/infection', 'relative'),
        context: $context,
        config: new ToolConfig('infection', true, null, [], 120),
    );

    expect($command->arguments()[0])->toBe('--logger-summary-json');
    expect($command->arguments()[2])->toBe('--min-msi=80');
    expect($command->artifacts())->toHaveKey('mutation_summary');
    expect($command->temporaryFiles())->toBe([$command->artifacts()['mutation_summary']]);
    expect(is_file($command->artifacts()['mutation_summary']))->toBeTrue();
});

it('keeps run command before injected infection output options', function (): void {
    $project = FixtureProject::create();
    $adapter = infectionAdapter($project);
    $context = $adapter->context(new CliArguments('infection', ['run', '--min-msi', '80']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('infection', $project->path('vendor/bin/infection'), 'vendor/bin/infection', 'relative'),
        context: $context,
        config: new ToolConfig('infection', true, null, [], 120),
    );

    expect($command->arguments()[0])->toBe('run');
    expect($command->arguments()[1])->toBe('--logger-summary-json');
    expect($command->arguments()[3])->toBe('--min-msi');
    expect($command->arguments()[4])->toBe('80');
});

it('respects explicit infection summary json output path', function (): void {
    $project = FixtureProject::create();
    $adapter = infectionAdapter($project);
    $context = $adapter->context(new CliArguments('infection', ['--logger-summary-json=build/infection.json']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('infection', $project->path('vendor/bin/infection'), 'vendor/bin/infection', 'relative'),
        context: $context,
        config: new ToolConfig('infection', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--logger-summary-json=build/infection.json']);
    expect($command->artifacts())->toBe(['mutation_summary' => $project->path('build/infection.json')]);
    expect($command->temporaryFiles())->toBe([]);
});

it('rejects non-run infection commands', function (): void {
    $project = FixtureProject::create();
    $adapter = infectionAdapter($project);
    $context = $adapter->context(new CliArguments('infection', ['configure']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('infection', $project->path('vendor/bin/infection'), 'vendor/bin/infection', 'relative'),
        context: $context,
        config: new ToolConfig('infection', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Infection adapter supports only the "run" command.');
});

it('parses infection msi threshold failures as failed status', function (): void {
    $project = FixtureProject::create();
    $reportPath = $project->writeJson('build/infection-summary.json', infectionToolAdapterJsonReport(msi: 72.0, coveredMsi: 81.5));
    $adapter = infectionAdapter($project);
    $context = $adapter->context(new CliArguments('infection', ['--min-msi=80']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, '', '', 0.12),
        context: $context,
        command: infectionPreparedCommand($project, $reportPath, ['--logger-summary-json', $reportPath, '--min-msi=80']),
    )->toPayload();

    expect($payload['tool'])->toBe('infection');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['msi' => 72.0, 'covered_msi' => 81.5]);
});

it('parses infection configured threshold failures as failed status from process output', function (): void {
    $project = FixtureProject::create();
    $reportPath = $project->writeJson('build/infection-summary.json', infectionToolAdapterJsonReport(msi: 72.0, coveredMsi: 81.5));
    $adapter = infectionAdapter($project);
    $context = $adapter->context(new CliArguments('infection'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, '', 'The minimum required MSI percentage should be 80%, but actual is 72%.', 0.12),
        context: $context,
        command: infectionPreparedCommand($project, $reportPath, ['--logger-summary-json', $reportPath]),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
});

it('treats unexpected non-zero infection exits as errors', function (): void {
    $project = FixtureProject::create();
    $reportPath = $project->writeJson('build/infection-summary.json', infectionToolAdapterJsonReport(msi: 100.0, coveredMsi: 100.0));
    $adapter = infectionAdapter($project);
    $context = $adapter->context(new CliArguments('infection'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, '', 'Could not run tests.', 0.12),
        context: $context,
        command: infectionPreparedCommand($project, $reportPath, ['--logger-summary-json', $reportPath]),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function infectionAdapter(FixtureProject $project): InfectionToolAdapter
{
    return new InfectionToolAdapter(tempFileFactory: new TempFileFactory($project->path('build/tmp')));
}

/**
 * @param list<string> $arguments
 */
function infectionPreparedCommand(FixtureProject $project, string $reportPath, array $arguments): PreparedCommand
{
    return new PreparedCommand(
        tool: 'infection',
        binary: $project->path('vendor/bin/infection'),
        arguments: $arguments,
        cwd: $project->root(),
        temporaryFiles: [],
        artifacts: ['mutation_summary' => $reportPath],
    );
}

/**
 * @return array<string, mixed>
 */
function infectionToolAdapterJsonReport(float $msi, float $coveredMsi): array
{
    return [
        'stats' => [
            'totalMutantsCount' => 4,
            'killedCount' => 2,
            'killedByStaticAnalysisCount' => 0,
            'notCoveredCount' => 0,
            'escapedCount' => 2,
            'errorCount' => 0,
            'syntaxErrorCount' => 0,
            'skippedCount' => 0,
            'ignoredCount' => 0,
            'timeOutCount' => 0,
            'msi' => $msi,
            'mutationCodeCoverage' => 100.0,
            'coveredCodeMsi' => $coveredMsi,
        ],
    ];
}
