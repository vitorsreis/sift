<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\ComposerUnused\ComposerUnusedToolAdapter;
use Tests\Support\FixtureProject;

it('describes composer-unused discovery metadata', function (): void {
    $definition = (new ComposerUnusedToolAdapter())->definition();

    expect($definition->name())->toBe('composer-unused');
    expect($definition->description())->toBe('Composer unused dependency analyzer.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/composer-unused.bat', 'vendor/bin/composer-unused', 'composer-unused']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev icanhazstring/composer-unused');
    expect($definition->defaultContext())->toBe('dependency');
});

it('prepares composer-unused with json output defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerUnusedToolAdapter();
    $context = $adapter->context(new CliArguments('composer-unused'), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer-unused', $project->path('vendor/bin/composer-unused'), 'vendor/bin/composer-unused', 'relative'),
        context: $context,
        config: new ToolConfig('composer-unused', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--output-format=json', '--no-progress']);
});

it('preserves explicit composer-unused json output format', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerUnusedToolAdapter();
    $context = $adapter->context(new CliArguments('composer-unused', ['composer.json', '-o', 'json']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer-unused', $project->path('vendor/bin/composer-unused'), 'vendor/bin/composer-unused', 'relative'),
        context: $context,
        config: new ToolConfig('composer-unused', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--no-progress', 'composer.json', '-o', 'json']);
});

it('rejects composer-unused output files because json must remain on stdout', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerUnusedToolAdapter();
    $context = $adapter->context(new CliArguments('composer-unused', ['--output-file=build/composer-unused.json']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('composer-unused', $project->path('vendor/bin/composer-unused'), 'vendor/bin/composer-unused', 'relative'),
        context: $context,
        config: new ToolConfig('composer-unused', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Composer Unused adapter requires JSON on stdout.');
});

it('parses composer-unused unused packages as failed status', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerUnusedToolAdapter();
    $context = $adapter->context(new CliArguments('composer-unused'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, composerUnusedJson(unusedPackages: ['vimeo/psalm']), '', 0.12),
        context: $context,
        command: composerUnusedPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('composer-unused');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['unused_packages' => 1]);
});

it('treats unexpected non-zero composer-unused exits without findings as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerUnusedToolAdapter();
    $context = $adapter->context(new CliArguments('composer-unused'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, composerUnusedJson(), '', 0.12),
        context: $context,
        command: composerUnusedPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function composerUnusedPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'composer-unused',
        binary: $project->path('vendor/bin/composer-unused'),
        arguments: ['--output-format=json', '--no-progress'],
        cwd: $project->root(),
    );
}

/**
 * @param list<string> $unusedPackages
 */
function composerUnusedJson(array $unusedPackages = []): string
{
    return json_encode([
        'used-packages' => [['name' => 'php']],
        'unused-packages' => $unusedPackages,
        'ignored-packages' => ['composer-plugin-api'],
    ], JSON_THROW_ON_ERROR);
}
