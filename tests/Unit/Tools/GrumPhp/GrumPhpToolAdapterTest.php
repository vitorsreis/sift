<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\GrumPhp\GrumPhpToolAdapter;
use Tests\Support\FixtureProject;

it('describes grumphp discovery metadata', function (): void {
    $definition = (new GrumPhpToolAdapter())->definition();

    expect($definition->name())->toBe('grumphp');
    expect($definition->description())->toBe('GrumPHP code quality task runner.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/grumphp.bat', 'vendor/bin/grumphp', 'grumphp']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev phpro/grumphp');
    expect($definition->defaultContext())->toBe('quality');
});

it('prepares the grumphp run command', function (): void {
    $project = FixtureProject::create();
    $adapter = new GrumPhpToolAdapter();
    $context = $adapter->context(new CliArguments('grumphp', ['--tasks=phpstan,phpunit']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('grumphp', $project->path('vendor/bin/grumphp'), 'vendor/bin/grumphp', 'relative'),
        context: $context,
        config: new ToolConfig('grumphp', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['run', '--no-ansi', '--tasks=phpstan,phpunit']);
});

it('rejects grumphp commands other than run outside raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new GrumPhpToolAdapter();
    $context = $adapter->context(new CliArguments('grumphp', ['git:init']), $project->root());

    expect(fn(): PreparedCommand => $adapter->prepare(
        tool: new LocatedTool('grumphp', $project->path('vendor/bin/grumphp'), 'vendor/bin/grumphp', 'relative'),
        context: $context,
        config: new ToolConfig('grumphp', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'GrumPHP adapter supports only the "run" command.');
});

it('parses failed grumphp tasks', function (): void {
    $project = FixtureProject::create();
    $adapter = new GrumPhpToolAdapter();
    $context = $adapter->context(new CliArguments('grumphp'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, grumPhpOutput(), '', 0.12),
        context: $context,
        command: grumPhpPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toBe(['tasks' => 2, 'passed' => 1, 'failed' => 1]);
    expect($payload['items'])->toContainEqual([
        'type' => 'error',
        'task' => 'phpstan',
        'message' => 'PHPStan found 1 error.',
    ]);
});

it('treats unexpected grumphp failures without task results as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new GrumPhpToolAdapter();
    $context = $adapter->context(new CliArguments('grumphp'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, '', 'Configuration file could not be loaded.', 0.12),
        context: $context,
        command: grumPhpPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function grumPhpPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'grumphp',
        binary: $project->path('vendor/bin/grumphp'),
        arguments: ['run', '--no-ansi'],
        cwd: $project->root(),
    );
}

function grumPhpOutput(): string
{
    return <<<'OUTPUT'
    Running task 1/2: phpunit... ✔
    Running task 2/2: phpstan... ✘
    phpstan
    =======
    PHPStan found 1 error.
    OUTPUT;
}
