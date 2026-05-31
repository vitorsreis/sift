<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\ComposerRequireChecker\ComposerRequireCheckerToolAdapter;
use Tests\Support\FixtureProject;

it('describes composer-require-checker discovery metadata', function (): void {
    $definition = (new ComposerRequireCheckerToolAdapter())->definition();

    expect($definition->name())->toBe('composer-require-checker');
    expect($definition->description())->toBe('Composer dependency requirement checker.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/composer-require-checker.bat', 'vendor/bin/composer-require-checker', 'composer-require-checker']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev maglnet/composer-require-checker');
    expect($definition->defaultContext())->toBe('dependency');
});

it('prepares composer-require-checker with json check defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerRequireCheckerToolAdapter();
    $context = $adapter->context(new CliArguments('composer-require-checker'), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer-require-checker', $project->path('vendor/bin/composer-require-checker'), 'vendor/bin/composer-require-checker', 'relative'),
        context: $context,
        config: new ToolConfig('composer-require-checker', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['check', '--output=json']);
});

it('keeps explicit composer-require-checker check command and json output', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerRequireCheckerToolAdapter();
    $context = $adapter->context(new CliArguments('composer-require-checker', ['check', '--output=json', 'composer.json']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer-require-checker', $project->path('vendor/bin/composer-require-checker'), 'vendor/bin/composer-require-checker', 'relative'),
        context: $context,
        config: new ToolConfig('composer-require-checker', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['check', '--output=json', 'composer.json']);
});

it('rejects composer-require-checker non-json output outside raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerRequireCheckerToolAdapter();
    $context = $adapter->context(new CliArguments('composer-require-checker', ['--output=text']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('composer-require-checker', $project->path('vendor/bin/composer-require-checker'), 'vendor/bin/composer-require-checker', 'relative'),
        context: $context,
        config: new ToolConfig('composer-require-checker', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Composer Require Checker adapter requires JSON output outside raw mode.');
});

it('rejects composer-require-checker commands outside check', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerRequireCheckerToolAdapter();
    $context = $adapter->context(new CliArguments('composer-require-checker', ['list']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('composer-require-checker', $project->path('vendor/bin/composer-require-checker'), 'vendor/bin/composer-require-checker', 'relative'),
        context: $context,
        config: new ToolConfig('composer-require-checker', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Composer Require Checker adapter supports only the "check" command.');
});

it('parses composer-require-checker unknown symbols as failed status', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerRequireCheckerToolAdapter();
    $context = $adapter->context(new CliArguments('composer-require-checker'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, composerRequireCheckerJson(['ctype_digit' => ['ext-ctype']]), '', 0.12),
        context: $context,
        command: composerRequireCheckerPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('composer-require-checker');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['unknown_symbols' => 1]);
});

it('treats unexpected non-zero composer-require-checker exits without findings as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerRequireCheckerToolAdapter();
    $context = $adapter->context(new CliArguments('composer-require-checker'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, composerRequireCheckerJson([]), '', 0.12),
        context: $context,
        command: composerRequireCheckerPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function composerRequireCheckerPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'composer-require-checker',
        binary: $project->path('vendor/bin/composer-require-checker'),
        arguments: ['check', '--output=json'],
        cwd: $project->root(),
    );
}

/**
 * @param array<string, mixed> $unknownSymbols
 */
function composerRequireCheckerJson(array $unknownSymbols): string
{
    return json_encode([
        '_meta' => [
            'composer-require-checker' => ['version' => '4.20.0'],
        ],
        'unknown-symbols' => $unknownSymbols,
    ], JSON_THROW_ON_ERROR);
}
