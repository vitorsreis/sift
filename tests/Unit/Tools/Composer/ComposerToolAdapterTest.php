<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\Composer\ComposerToolAdapter;
use Tests\Support\FixtureProject;

it('describes composer discovery metadata', function (): void {
    $definition = (new ComposerToolAdapter())->definition();

    expect($definition->name())->toBe('composer');
    expect($definition->description())->toBe('Composer package metadata and audit runner.');
    expect($definition->binaryCandidates())->toBe(['composer.cmd', 'composer.bat', 'composer']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('Install Composer from https://getcomposer.org/download/.');
    expect($definition->defaultContext())->toBe('dependency');
});

it('prepares composer audit by default with json output', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer'), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer', 'composer', 'composer', 'system'),
        context: $context,
        config: new ToolConfig('composer', true, null, [], 120),
    );

    expect($context->subcommand())->toBe('audit');
    expect($command->arguments())->toBe(['audit', '--format=json']);
});

it('prepares composer read-only subcommands with json output', function (string $subcommand): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', [$subcommand]), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer', 'composer', 'composer', 'system'),
        context: $context,
        config: new ToolConfig('composer', true, null, [], 120),
    );

    expect($context->subcommand())->toBe($subcommand);
    expect($command->arguments())->toBe([$subcommand, '--format=json']);
})->with(['audit', 'licenses', 'outdated', 'show']);

it('prepares composer validate without json output', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', ['validate', '--strict']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer', 'composer', 'composer', 'system'),
        context: $context,
        config: new ToolConfig('composer', true, null, [], 120),
    );

    expect($context->subcommand())->toBe('validate');
    expect($command->arguments())->toBe(['validate', '--strict']);
});

it('maps composer show --outdated to outdated mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', ['show', '--outdated']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer', 'composer', 'composer', 'system'),
        context: $context,
        config: new ToolConfig('composer', true, null, [], 120),
    );

    expect($context->subcommand())->toBe('show');
    expect($context->mode())->toBe('outdated');
    expect($command->arguments())->toBe(['show', '--format=json', '--outdated']);
});

it('preserves explicit composer json format', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', ['audit', '--format', 'json', '--no-dev']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer', 'composer', 'composer', 'system'),
        context: $context,
        config: new ToolConfig('composer', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['audit', '--format', 'json', '--no-dev']);
});

it('rejects non-json composer formats outside raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', ['audit', '--format=summary']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('composer', 'composer', 'composer', 'system'),
        context: $context,
        config: new ToolConfig('composer', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Composer adapter requires JSON output outside raw mode.');
});

it('rejects mutating composer commands even in raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', ['require', 'vendor/package'], ['--raw' => true]), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('composer', 'composer', 'composer', 'system'),
        context: $context,
        config: new ToolConfig('composer', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Composer adapter supports only audit, licenses, outdated, show and validate.');
});

it('parses composer audit findings as failed status', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', ['audit']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, composerToolAuditJson(), '', 0.12),
        context: $context,
        command: composerToolPreparedCommand($project, ['audit', '--format=json']),
    )->toPayload();

    expect($payload['tool'])->toBe('composer');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['findings' => 1]);
});

it('treats unexpected non-zero composer exits without findings as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', ['licenses']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, composerToolLicensesJson(), '', 0.12),
        context: $context,
        command: composerToolPreparedCommand($project, ['licenses', '--format=json']),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

it('parses composer validate success output', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerToolAdapter();
    $context = $adapter->context(new CliArguments('composer', ['validate', '--strict']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, './composer.json is valid', '', 0.12),
        context: $context,
        command: composerToolPreparedCommand($project, ['validate', '--strict']),
    )->toPayload();

    expect($payload['tool'])->toBe('composer');
    expect($payload['status'])->toBe(RunStatus::Passed->value);
    expect($payload['summary'])->toMatchArray(['valid' => true, 'findings' => 0]);
});

/**
 * @param list<string> $arguments
 */
function composerToolPreparedCommand(FixtureProject $project, array $arguments): PreparedCommand
{
    return new PreparedCommand(
        tool: 'composer',
        binary: 'composer',
        arguments: $arguments,
        cwd: $project->root(),
    );
}

function composerToolAuditJson(): string
{
    return json_encode([
        'advisories' => [],
        'abandoned' => [
            'azjezz/psl' => 'php-standard-library/php-standard-library',
        ],
    ], JSON_THROW_ON_ERROR);
}

function composerToolLicensesJson(): string
{
    return json_encode([
        'name' => 'vitorsreis/sift',
        'version' => 'dev-master',
        'license' => ['MIT'],
        'dependencies' => [],
    ], JSON_THROW_ON_ERROR);
}
