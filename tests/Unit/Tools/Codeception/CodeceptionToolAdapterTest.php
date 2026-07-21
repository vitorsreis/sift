<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\CliArguments;
use Sift\Tools\Codeception\CodeceptionToolAdapter;
use Sift\Tools\Testing\TestRunnerCommandFactory;
use Tests\Support\FixtureProject;

it('describes codeception discovery metadata and alias', function (): void {
    $definition = (new CodeceptionToolAdapter())->definition();

    expect($definition->name())->toBe('codeception');
    expect($definition->aliases())->toBe(['codecept']);
    expect($definition->description())->toBe('Codeception full-stack test runner.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/codecept.bat', 'vendor/bin/codecept', 'codecept']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev codeception/codeception');
    expect($definition->defaultContext())->toBe('test');
});

it('prepares codeception run with junit and clover reports', function (): void {
    $project = FixtureProject::create();
    $adapter = new CodeceptionToolAdapter(commandFactory: codeceptionCommandFactory($project));
    $context = $adapter->context(new CliArguments('codeception', ['run', 'acceptance', '--coverage', '--min=80']), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('codeception', $project->path('vendor/bin/codecept'), 'vendor/bin/codecept', 'relative'),
        context: $context,
        config: new ToolConfig('codeception', true, null, [], 120),
    );

    expect($context->coverage())->toBeTrue();
    expect($context->coverageMin())->toBe(80.0);
    expect($command->arguments()[0])->toBe('run');
    expect($command->arguments())->toContain('acceptance', '--coverage');
    expect(implode(' ', $command->arguments()))->toContain('--xml=', '--coverage-xml=');
    expect($command->arguments())->not->toContain('--min=80');
    expect($command->artifacts())->toHaveKeys(['junit', 'coverage_clover']);
});

it('parses codeception junit results', function (): void {
    $project = FixtureProject::create();
    $adapter = new CodeceptionToolAdapter(commandFactory: codeceptionCommandFactory($project));
    $context = $adapter->context(new CliArguments('codecept'), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('codeception', $project->path('vendor/bin/codecept'), 'vendor/bin/codecept', 'relative'),
        context: $context,
        config: new ToolConfig('codeception', true, null, [], 120),
    );

    file_put_contents($command->artifacts()['junit'], <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites><testsuite tests="1"><testcase name="checkout succeeds" file="tests/Acceptance/CheckoutCest.php"/></testsuite></testsuites>
XML);

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, '', '', 0.1),
        context: $context,
        command: $command,
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Passed->value);
    expect($payload['summary'])->toMatchArray(['tests' => 1, 'passed' => 1]);
});

function codeceptionCommandFactory(FixtureProject $project): TestRunnerCommandFactory
{
    return new TestRunnerCommandFactory(new TempFileFactory($project->path('build/tmp')));
}
