<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\RunStatus;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\CliArguments;
use Sift\Tools\Testing\PhpunitToolAdapter;
use Sift\Tools\Testing\TestRunnerCommandFactory;
use Tests\Support\FixtureProject;

it('describes phpunit discovery and metadata', function (): void {
    $definition = (new PhpunitToolAdapter())->definition();

    expect($definition->name())->toBe('phpunit');
    expect($definition->description())->toBe('PHPUnit test runner.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/phpunit.bat', 'vendor/bin/phpunit', 'phpunit']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev phpunit/phpunit');
    expect($definition->defaultContext())->toBe('test');
});

it('prepares phpunit with junit output', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpunitToolAdapter(commandFactory: phpunitAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('phpunit', ['--filter', 'CheckoutTest']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('phpunit', $project->path('vendor/bin/phpunit'), 'vendor/bin/phpunit', 'relative'),
        context: $context,
        config: new ToolConfig('phpunit', true, null, [], 120),
    );

    expect($command->tool())->toBe('phpunit');
    expect($command->arguments()[0])->toBe('--filter');
    expect($command->arguments()[2])->toBe('--log-junit');
    expect($command->artifacts())->toHaveKey('junit');
    expect($command->temporaryFiles())->toBe([$command->artifacts()['junit']]);
});

it('treats phpunit separate min coverage as a sift threshold without passing it to phpunit', function (): void {
    expectPhpunitMinCoverageArgumentIsSiftOnly(['--coverage', '--min', '80']);
});

it('treats phpunit inline min coverage as a sift threshold without passing it to phpunit', function (): void {
    expectPhpunitMinCoverageArgumentIsSiftOnly(['--coverage', '--min=80']);
});

/**
 * @param list<string> $arguments
 */
function expectPhpunitMinCoverageArgumentIsSiftOnly(array $arguments): void
{
    $project = FixtureProject::create();
    $adapter = new PhpunitToolAdapter(commandFactory: phpunitAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('phpunit', $arguments), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('phpunit', $project->path('vendor/bin/phpunit'), 'vendor/bin/phpunit', 'relative'),
        context: $context,
        config: new ToolConfig('phpunit', true, null, [], 120),
    );

    expect($context->coverageMin())->toBe(80.0);
    expect($command->arguments())->not->toContain('--min');
    expect(implode(' ', $command->arguments()))->not->toContain('--min=80');
    expect($command->artifacts())->toHaveKeys(['junit', 'coverage_clover']);
}

it('parses phpunit junit results and removes temporary reports', function (): void {
    $project = FixtureProject::create();
    $testFile = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $adapter = new PhpunitToolAdapter(commandFactory: phpunitAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('phpunit'), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('phpunit', $project->path('vendor/bin/phpunit'), 'vendor/bin/phpunit', 'relative'),
        context: $context,
        config: new ToolConfig('phpunit', true, null, [], 120),
    );

    file_put_contents($command->artifacts()['junit'], sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite tests="1" failures="1">
    <testcase name="it checks out" file="%s" line="17">
      <failure message="Expected true">Failed asserting that false is true.</failure>
    </testcase>
  </testsuite>
</testsuites>
XML,
        htmlspecialchars($testFile, ENT_QUOTES | ENT_XML1),
    ));

    $result = $adapter->parse(
        execution: ExecutionResult::completed(1, '', '', 0.12),
        context: $context,
        command: $command,
    );
    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('phpunit');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toBe([
        'tests' => 1,
        'passed' => 0,
        'failures' => 1,
        'errors' => 0,
        'skipped' => 0,
    ]);
    expect($payload['items'][0]['file'])->toBe('tests/Feature/CheckoutTest.php');
    expect(is_file($command->artifacts()['junit']))->toBeFalse();
});

it('removes temporary reports when phpunit parsing fails', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpunitToolAdapter(commandFactory: phpunitAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('phpunit'), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('phpunit', $project->path('vendor/bin/phpunit'), 'vendor/bin/phpunit', 'relative'),
        context: $context,
        config: new ToolConfig('phpunit', true, null, [], 120),
    );

    file_put_contents($command->artifacts()['junit'], 'not xml');

    expect(fn(): mixed => $adapter->parse(
        execution: ExecutionResult::completed(1, '', '', 0.12),
        context: $context,
        command: $command,
    ))->toThrow(UserFacingException::class);
    expect(is_file($command->artifacts()['junit']))->toBeFalse();
});

it('reports native phpunit option errors instead of the startup banner', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpunitToolAdapter(commandFactory: phpunitAdapterCommandFactory($project));
    $context = $adapter->context(new CliArguments('phpunit', ['--coverage-text', '--minimum-coverage=80']), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('phpunit', $project->path('vendor/bin/phpunit'), 'vendor/bin/phpunit', 'relative'),
        context: $context,
        config: new ToolConfig('phpunit', true, null, [], 120),
    );

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(
            2,
            "PHPUnit 13.2.1 by Sebastian Bergmann and contributors.\n\nUnknown option \"--minimum-coverage\". Most similar options are --no-coverage, --strict-coverage, --branch-coverage, --path-coverage, --covers",
            '',
            0.23,
        ),
        context: $context,
        command: $command,
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
    expect($payload['items'][0])->toMatchArray([
        'type' => 'error',
        'message' => 'Unknown option "--minimum-coverage". Most similar options are --no-coverage, --strict-coverage, --branch-coverage, --path-coverage, --covers',
    ]);
    expect(is_file($command->artifacts()['junit']))->toBeFalse();
    expect(is_file($command->artifacts()['coverage_clover']))->toBeFalse();
});

function phpunitAdapterCommandFactory(FixtureProject $project): TestRunnerCommandFactory
{
    return new TestRunnerCommandFactory(new TempFileFactory($project->path('build/tmp')));
}
