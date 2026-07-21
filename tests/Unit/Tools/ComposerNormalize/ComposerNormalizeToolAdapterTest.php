<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\ComposerNormalize\ComposerNormalizeToolAdapter;
use Sift\Tools\MutationPolicy;
use Tests\Support\FixtureProject;

it('describes composer normalize discovery metadata', function (): void {
    $definition = (new ComposerNormalizeToolAdapter())->definition();

    expect($definition->name())->toBe('composer-normalize');
    expect($definition->aliases())->toBe(['normalize']);
    expect($definition->description())->toBe('Composer manifest normalizer.');
    expect($definition->binaryCandidates())->toBe(['composer.cmd', 'composer.bat', 'composer']);
    expect($definition->versionCommand())->toBe(['show', 'ergebnis/composer-normalize', '--format=json']);
    expect($definition->installHint())->toBe('composer require --dev ergebnis/composer-normalize');
    expect($definition->defaultContext())->toBe('style');
    expect($definition->mutationPolicy())->toBe(MutationPolicy::RepairFlag);
});

it('prepares composer normalize in safe dry-run mode by default', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerNormalizeToolAdapter();
    $context = $adapter->context(new CliArguments('composer-normalize'), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer-normalize', 'composer', 'composer', 'path'),
        context: $context,
        config: new ToolConfig('composer-normalize', true, null, [], 120),
    );

    expect($context->repair())->toBeFalse();
    expect($command->arguments())->toBe(['normalize', '--no-progress', '--no-ansi', '--no-interaction', '--dry-run', '--diff']);
});

it('prepares composer normalize repair only when explicit', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerNormalizeToolAdapter();
    $context = $adapter->context(new CliArguments('composer-normalize', ['--repair', 'fixtures/composer.json']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('composer-normalize', 'composer', 'composer', 'path'),
        context: $context,
        config: new ToolConfig('composer-normalize', true, null, [], 120),
    );

    expect($context->repair())->toBeTrue();
    expect($command->arguments())->toBe(['normalize', '--no-progress', '--no-ansi', '--no-interaction', '--diff', 'fixtures/composer.json']);
});

it('parses an unnormalized composer manifest as a finding', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerNormalizeToolAdapter();
    $context = $adapter->context(new CliArguments('composer-normalize'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, '', composerNormalizeDiff(), 0.12),
        context: $context,
        command: composerNormalizePreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toBe(['files' => 1, 'diffs' => 1]);
    expect($payload['items'][0])->toMatchArray([
        'type' => 'issue',
        'file' => 'composer.json',
        'message' => 'composer.json is not normalized.',
    ]);
    expect($payload['items'][1])->toMatchArray(['type' => 'diff', 'file' => 'composer.json']);
});

it('reports repaired composer manifests as changed', function (): void {
    $project = FixtureProject::create();
    $adapter = new ComposerNormalizeToolAdapter();
    $context = $adapter->context(new CliArguments('composer-normalize', ['--repair']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, '', composerNormalizeDiff(), 0.12),
        context: $context,
        command: composerNormalizePreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Changed->value);
});

function composerNormalizePreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'composer-normalize',
        binary: 'composer',
        arguments: ['normalize', '--dry-run', '--diff'],
        cwd: $project->root(),
    );
}

function composerNormalizeDiff(): string
{
    return <<<'OUTPUT'
composer.json is not normalized.
--- Original
+++ Normalized
@@ -1,3 +1,3 @@
-{"require":{},"name":"acme/app"}
+{"name":"acme/app","require":{}}
OUTPUT;
}
