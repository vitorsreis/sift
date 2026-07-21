<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\CliArguments;
use Sift\Tools\Testing\ParatestToolAdapter;
use Sift\Tools\Testing\TestRunnerCommandFactory;
use Tests\Support\FixtureProject;

it('describes paratest discovery metadata', function (): void {
    $definition = (new ParatestToolAdapter())->definition();

    expect($definition->name())->toBe('paratest');
    expect($definition->description())->toBe('ParaTest parallel test runner.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/paratest.bat', 'vendor/bin/paratest', 'paratest']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev brianium/paratest');
    expect($definition->defaultContext())->toBe('test');
});

it('prepares paratest with junit output', function (): void {
    $project = FixtureProject::create();
    $adapter = new ParatestToolAdapter(commandFactory: paratestAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('paratest', ['--filter', 'CheckoutTest']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('paratest', $project->path('vendor/bin/paratest'), 'vendor/bin/paratest', 'relative'),
        context: $context,
        config: new ToolConfig('paratest', true, null, [], 120),
    );

    expect($context->filter())->toBe('CheckoutTest');
    expect($command->arguments())->toContain('--no-progress', '--colors=never', '--filter', 'CheckoutTest', '--log-junit');
    expect($command->artifacts())->toHaveKey('junit');
});

it('prepares paratest with clover output for coverage runs', function (): void {
    $project = FixtureProject::create();
    $adapter = new ParatestToolAdapter(commandFactory: paratestAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('paratest', ['--coverage', '--min=75']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('paratest', $project->path('vendor/bin/paratest'), 'vendor/bin/paratest', 'relative'),
        context: $context,
        config: new ToolConfig('paratest', true, null, [], 120),
    );

    expect($context->coverage())->toBeTrue();
    expect($context->coverageMin())->toBe(75.0);
    expect($command->artifacts())->toHaveKeys(['junit', 'coverage_clover']);
});

it('parses paratest junit results', function (): void {
    $project = FixtureProject::create();
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $adapter = new ParatestToolAdapter(commandFactory: paratestAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('paratest'), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('paratest', $project->path('vendor/bin/paratest'), 'vendor/bin/paratest', 'relative'),
        context: $context,
        config: new ToolConfig('paratest', true, null, [], 120),
    );

    file_put_contents($command->artifacts()['junit'], sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites><testsuite tests="1"><testcase name="it checks out" file="%s"/></testsuite></testsuites>
XML,
        htmlspecialchars($test, ENT_QUOTES | ENT_XML1),
    ));

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, '', '', 0.12),
        context: $context,
        command: $command,
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Passed->value);
    expect($payload['summary'])->toBe([
        'tests' => 1,
        'passed' => 1,
        'failures' => 0,
        'errors' => 0,
        'skipped' => 0,
    ]);
});

function paratestAdapterCommandFactory(FixtureProject $project): TestRunnerCommandFactory
{
    return new TestRunnerCommandFactory(new TempFileFactory($project->path('build/tmp')));
}
