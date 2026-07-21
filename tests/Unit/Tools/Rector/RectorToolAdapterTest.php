<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\Rector\RectorToolAdapter;
use Tests\Support\FixtureProject;

it('describes rector discovery metadata', function (): void {
    $definition = (new RectorToolAdapter())->definition();

    expect($definition->name())->toBe('rector');
    expect($definition->description())->toBe('Rector refactoring dry-run analyser.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/rector.bat', 'vendor/bin/rector', 'rector']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev rector/rector');
    expect($definition->defaultContext())->toBe('refactor');
});

it('prepares rector process with dry-run json defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new RectorToolAdapter();
    $context = $adapter->context(new CliArguments('rector', ['src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('rector', $project->path('vendor/bin/rector'), 'vendor/bin/rector', 'relative'),
        context: $context,
        config: new ToolConfig('rector', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['process', '--dry-run', '--output-format=json', '--no-progress-bar', '--no-ansi', 'src']);
});

it('keeps rector machine output controls out of raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new RectorToolAdapter();
    $context = $adapter->context(
        new CliArguments('rector', ['process', '--dry-run', 'src'], ['raw' => true]),
        $project->root(),
    );

    $command = $adapter->prepare(
        tool: new LocatedTool('rector', $project->path('vendor/bin/rector'), 'vendor/bin/rector', 'relative'),
        context: $context,
        config: new ToolConfig('rector', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['process', '--dry-run', 'src']);
});

it('forces rector progress and ansi output off outside raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new RectorToolAdapter();
    $context = $adapter->context(
        new CliArguments('rector', ['process', '--dry-run', '--ansi', '--no-progress-bar', 'src']),
        $project->root(),
    );

    $command = $adapter->prepare(
        tool: new LocatedTool('rector', $project->path('vendor/bin/rector'), 'vendor/bin/rector', 'relative'),
        context: $context,
        config: new ToolConfig('rector', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['process', '--output-format=json', '--no-progress-bar', '--no-ansi', '--dry-run', 'src']);
});

it('rejects rector subcommands other than process', function (): void {
    $project = FixtureProject::create();
    $adapter = new RectorToolAdapter();
    $context = $adapter->context(new CliArguments('rector', ['list']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('rector', $project->path('vendor/bin/rector'), 'vendor/bin/rector', 'relative'),
        context: $context,
        config: new ToolConfig('rector', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Rector adapter supports only the "process" command.');
});

it('parses rector changes as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new RectorToolAdapter();
    $context = $adapter->context(new CliArguments('rector'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, rectorJson($source, changedFiles: 1, errors: 0), '', 0.12),
        context: $context,
        command: rectorPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('rector');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['changed_files' => 1, 'errors' => 0]);
});

it('fails when rector includes diffs but reports a zero total', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new RectorToolAdapter();
    $context = $adapter->context(new CliArguments('rector'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, rectorJsonWithInconsistentChangedFiles($source), '', 0.12),
        context: $context,
        command: rectorPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['changed_files' => 1, 'errors' => 0]);
});

it('parses rector errors as error status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new RectorToolAdapter();
    $context = $adapter->context(new CliArguments('rector'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, rectorJson($source, changedFiles: 0, errors: 1), '', 0.12),
        context: $context,
        command: rectorPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

it('treats unexpected non-zero rector exits without findings as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new RectorToolAdapter();
    $context = $adapter->context(new CliArguments('rector'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, rectorJson(null, changedFiles: 0, errors: 0), '', 0.12),
        context: $context,
        command: rectorPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function rectorPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'rector',
        binary: $project->path('vendor/bin/rector'),
        arguments: ['process', '--dry-run', '--output-format=json'],
        cwd: $project->root(),
    );
}

function rectorJson(?string $source, int $changedFiles, int $errors): string
{
    $document = [
        'totals' => [
            'changed_files' => $changedFiles,
            'errors' => $errors,
        ],
    ];

    if ($source !== null && $changedFiles > 0) {
        $document['changed_files'] = [$source];
        $document['file_diffs'] = [
            [
                'file' => $source,
                'diff' => "--- Original\n+++ New",
                'applied_rectors' => ['Rector\\ExampleRector'],
            ],
        ];
    }

    if ($source !== null && $errors > 0) {
        $document['errors'] = [
            [
                'message' => 'Could not parse file.',
                'file' => $source,
            ],
        ];
    }

    return json_encode($document, JSON_THROW_ON_ERROR);
}

function rectorJsonWithInconsistentChangedFiles(string $source): string
{
    return json_encode([
        'totals' => [
            'changed_files' => 0,
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
