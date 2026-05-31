<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\Psalm\PsalmToolAdapter;
use Tests\Support\FixtureProject;

it('describes psalm discovery metadata', function (): void {
    $definition = (new PsalmToolAdapter())->definition();

    expect($definition->name())->toBe('psalm');
    expect($definition->description())->toBe('Psalm static analyser.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/psalm.bat', 'vendor/bin/psalm', 'psalm']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev vimeo/psalm');
    expect($definition->defaultContext())->toBe('analysis');
});

it('prepares psalm with json defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new PsalmToolAdapter();
    $context = $adapter->context(new CliArguments('psalm', ['src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('psalm', $project->path('vendor/bin/psalm'), 'vendor/bin/psalm', 'relative'),
        context: $context,
        config: new ToolConfig('psalm', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--output-format=json', '--no-progress', 'src']);
});

it('parses psalm findings as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PsalmToolAdapter();
    $context = $adapter->context(new CliArguments('psalm'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, psalmJson($source), '', 0.12),
        context: $context,
        command: psalmPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('psalm');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['issues' => 1, 'errors' => 1]);
});

it('treats unexpected non-zero psalm exits without findings as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new PsalmToolAdapter();
    $context = $adapter->context(new CliArguments('psalm'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, '[]', '', 0.12),
        context: $context,
        command: psalmPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function psalmPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'psalm',
        binary: $project->path('vendor/bin/psalm'),
        arguments: ['--output-format=json', '--no-progress'],
        cwd: $project->root(),
    );
}

function psalmJson(string $source): string
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
