<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\Testing\TestRunnerCommandFactory;
use Sift\Tools\ToolContext;
use Tests\Support\FixtureProject;

it('injects junit output when it is missing', function (): void {
    $project = FixtureProject::create();
    $factory = testRunnerFactory($project);

    $command = $factory->prepare(
        toolName: 'pest',
        tool: testRunnerLocatedTool($project),
        context: new ToolContext('pest', userArgs: ['--filter', 'CheckoutTest'], cwd: $project->root()),
        config: new ToolConfig('pest', true, null, [], 120),
        arguments: ['--filter', 'CheckoutTest'],
    );

    expect($command->arguments()[0])->toBe('--filter');
    expect($command->arguments()[1])->toBe('CheckoutTest');
    expect($command->arguments()[2])->toBe('--log-junit');
    expect($command->artifacts())->toHaveKey('junit');
    expect($command->temporaryFiles())->toBe([$command->artifacts()['junit']]);
    expect(is_file($command->artifacts()['junit']))->toBeTrue();
});

it('respects explicit junit output paths', function (): void {
    $project = FixtureProject::create();
    $factory = testRunnerFactory($project);

    $command = $factory->prepare(
        toolName: 'phpunit',
        tool: testRunnerLocatedTool($project),
        context: new ToolContext('phpunit', cwd: $project->root()),
        config: new ToolConfig('phpunit', true, null, [], 120),
        arguments: ['--log-junit', 'build/junit.xml'],
    );

    expect($command->arguments())->toBe(['--log-junit', 'build/junit.xml']);
    expect($command->artifacts())->toBe(['junit' => $project->path('build/junit.xml')]);
    expect($command->temporaryFiles())->toBe([]);
});

it('respects inline junit output paths', function (): void {
    $project = FixtureProject::create();
    $factory = testRunnerFactory($project);

    $command = $factory->prepare(
        toolName: 'phpunit',
        tool: testRunnerLocatedTool($project),
        context: new ToolContext('phpunit', cwd: $project->root()),
        config: new ToolConfig('phpunit', true, null, [], 120),
        arguments: ['--log-junit=build/junit.xml'],
    );

    expect($command->arguments())->toBe(['--log-junit=build/junit.xml']);
    expect($command->artifacts())->toBe(['junit' => $project->path('build/junit.xml')]);
    expect($command->temporaryFiles())->toBe([]);
});

it('injects clover output when coverage is requested', function (): void {
    $project = FixtureProject::create();
    $factory = testRunnerFactory($project);

    $command = $factory->prepare(
        toolName: 'pest',
        tool: testRunnerLocatedTool($project),
        context: new ToolContext('pest', cwd: $project->root(), coverage: true, coverageMin: 80.0),
        config: new ToolConfig('pest', true, null, [], 120),
        arguments: ['--coverage', '--min', '80'],
    );

    expect($command->arguments()[0])->toBe('--coverage');
    expect($command->arguments()[3])->toBe('--log-junit');
    expect($command->arguments()[5])->toBe('--coverage-clover');
    expect($command->artifacts())->toHaveKeys(['junit', 'coverage_clover']);
    expect($command->temporaryFiles())->toBe([
        $command->artifacts()['junit'],
        $command->artifacts()['coverage_clover'],
    ]);
});

it('fails early when output options miss their values', function (): void {
    $project = FixtureProject::create();
    $factory = testRunnerFactory($project);

    expect(fn(): mixed => $factory->prepare(
        toolName: 'phpunit',
        tool: testRunnerLocatedTool($project),
        context: new ToolContext('phpunit', cwd: $project->root()),
        config: new ToolConfig('phpunit', true, null, [], 120),
        arguments: ['--log-junit', '--filter', 'CheckoutTest'],
    ))->toThrow(InvalidUsageException::class, 'Argument "--log-junit" requires a value.');
});

function testRunnerFactory(FixtureProject $project): TestRunnerCommandFactory
{
    return new TestRunnerCommandFactory(new TempFileFactory($project->path('build/tmp')));
}

function testRunnerLocatedTool(FixtureProject $project): LocatedTool
{
    return new LocatedTool('pest', $project->path('vendor/bin/pest'), 'vendor/bin/pest', 'relative');
}
