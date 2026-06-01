<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\MutationPolicy;
use Sift\Tools\Pint\PintToolAdapter;
use Tests\Support\FixtureProject;

it('describes pint discovery metadata', function (): void {
    $definition = (new PintToolAdapter())->definition();

    expect($definition->name())->toBe('pint');
    expect($definition->description())->toBe('Laravel Pint code style formatter.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/pint.bat', 'vendor/bin/pint', 'pint']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev laravel/pint');
    expect($definition->defaultContext())->toBe('style');
    expect($definition->mutationPolicy())->toBe(MutationPolicy::RepairFlag);
    expect($definition->repairCommand())->toBe(['--repair']);
});

it('prepares pint in safe test mode by default', function (): void {
    $project = FixtureProject::create();
    $adapter = new PintToolAdapter();
    $context = $adapter->context(new CliArguments('pint', ['src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('pint', $project->path('vendor/bin/pint'), 'vendor/bin/pint', 'relative'),
        context: $context,
        config: new ToolConfig('pint', true, null, [], 120),
    );

    expect($context->repair())->toBeFalse();
    expect($command->arguments())->toBe(['--test', '--format=json', 'src']);
});

it('prepares pint repair only when repair is explicit', function (): void {
    $project = FixtureProject::create();
    $adapter = new PintToolAdapter();
    $context = $adapter->context(new CliArguments('pint', ['--repair', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('pint', $project->path('vendor/bin/pint'), 'vendor/bin/pint', 'relative'),
        context: $context,
        config: new ToolConfig('pint', true, null, [], 120),
    );

    expect($context->repair())->toBeTrue();
    expect($command->arguments())->toBe(['--repair', '--format=json', 'src']);
});

it('preserves explicit json format without duplicating it', function (): void {
    $project = FixtureProject::create();
    $adapter = new PintToolAdapter();
    $context = $adapter->context(new CliArguments('pint', ['--test', '--format=json', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('pint', $project->path('vendor/bin/pint'), 'vendor/bin/pint', 'relative'),
        context: $context,
        config: new ToolConfig('pint', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--test', '--format=json', 'src']);
});

it('parses pint style failures as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PintToolAdapter();
    $context = $adapter->context(new CliArguments('pint'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, pintJson($source), '', 0.12),
        context: $context,
        command: pintPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('pint');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['result' => 'fail', 'files' => 1]);
});

it('parses pint repair changes as changed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PintToolAdapter();
    $context = $adapter->context(new CliArguments('pint', ['--repair']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, pintJson($source), '', 0.12),
        context: $context,
        command: pintPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Changed->value);
});

it('treats unexpected non-zero pint exits without files as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new PintToolAdapter();
    $context = $adapter->context(new CliArguments('pint'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, pintJson(null), '', 0.12),
        context: $context,
        command: pintPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function pintPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'pint',
        binary: $project->path('vendor/bin/pint'),
        arguments: ['--test', '--format=json'],
        cwd: $project->root(),
    );
}

function pintJson(?string $source): string
{
    $document = [
        'about' => 'PHP CS Fixer 3.75.0',
        'files' => [],
        'time' => [
            'total' => 0.123,
        ],
        'memory' => 12.345,
    ];

    if ($source !== null) {
        $document['files'] = [
            [
                'path' => $source,
                'fixers' => ['ordered_imports'],
            ],
        ];
    }

    return json_encode($document, JSON_THROW_ON_ERROR);
}
