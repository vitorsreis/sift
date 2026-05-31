<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\CliArguments;
use Sift\Tools\Testing\PestToolAdapter;
use Sift\Tools\Testing\TestRunnerCommandFactory;
use Tests\Support\FixtureProject;

it('describes pest discovery metadata and aliases', function (): void {
    $definition = (new PestToolAdapter())->definition();

    expect($definition->name())->toBe('pest');
    expect($definition->aliases())->toBe(['test', 'tests']);
    expect($definition->description())->toBe('Pest test runner.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/pest.bat', 'vendor/bin/pest', 'pest']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev pestphp/pest');
    expect($definition->defaultContext())->toBe('test');
});

it('prepares pest with junit and clover output for coverage runs', function (): void {
    $project = FixtureProject::create();
    $adapter = new PestToolAdapter(commandFactory: pestAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('pest', ['--coverage', '--min', '80']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('pest', $project->path('vendor/bin/pest'), 'vendor/bin/pest', 'relative'),
        context: $context,
        config: new ToolConfig('pest', true, null, [], 120),
    );

    expect($context->coverage())->toBeTrue();
    expect($context->coverageMin())->toBe(80.0);
    expect($command->artifacts())->toHaveKeys(['junit', 'coverage_clover']);
    expect($command->temporaryFiles())->toBe([
        $command->artifacts()['junit'],
        $command->artifacts()['coverage_clover'],
    ]);
});

it('parses pest junit and clover results', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $adapter = new PestToolAdapter(commandFactory: pestAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('pest', ['--coverage', '--min', '80']), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('pest', $project->path('vendor/bin/pest'), 'vendor/bin/pest', 'relative'),
        context: $context,
        config: new ToolConfig('pest', true, null, [], 120),
    );

    file_put_contents($command->artifacts()['junit'], sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites><testsuite tests="1"><testcase name="it checks out" file="%s"/></testsuite></testsuites>
XML,
        htmlspecialchars($test, ENT_QUOTES | ENT_XML1),
    ));
    file_put_contents($command->artifacts()['coverage_clover'], sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="%s"><metrics statements="10" coveredstatements="7"/></file>
    <metrics statements="10" coveredstatements="7"/>
  </project>
</coverage>
XML,
        htmlspecialchars($source, ENT_QUOTES | ENT_XML1),
    ));

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, '', '', 0.12),
        context: $context,
        command: $command,
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray([
        'tests' => 1,
        'passed' => 1,
        'coverage_percent' => 70.0,
        'coverage_min' => 80.0,
        'coverage_files_below_min' => 1,
    ]);
    expect($payload['items'])->toBe([
        [
            'type' => 'coverage',
            'file' => 'src/Checkout.php',
            'percent' => 70.0,
        ],
    ]);
    expect(is_file($command->artifacts()['junit']))->toBeFalse();
    expect(is_file($command->artifacts()['coverage_clover']))->toBeFalse();
});

function pestAdapterCommandFactory(FixtureProject $project): TestRunnerCommandFactory
{
    return new TestRunnerCommandFactory(new TempFileFactory($project->path('build/tmp')));
}
