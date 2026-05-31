<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\Deptrac\DeptracToolAdapter;
use Tests\Support\FixtureProject;

it('describes deptrac discovery metadata', function (): void {
    $definition = (new DeptracToolAdapter())->definition();

    expect($definition->name())->toBe('deptrac');
    expect($definition->description())->toBe('Deptrac architecture dependency analyser.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/deptrac.bat', 'vendor/bin/deptrac', 'deptrac']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev deptrac/deptrac');
    expect($definition->defaultContext())->toBe('architecture');
});

it('prepares deptrac with json analyse defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new DeptracToolAdapter();
    $context = $adapter->context(new CliArguments('deptrac'), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('deptrac', $project->path('vendor/bin/deptrac'), 'vendor/bin/deptrac', 'relative'),
        context: $context,
        config: new ToolConfig('deptrac', true, null, [], 120),
    );

    expect($context->subcommand())->toBe('analyse');
    expect($command->arguments())->toBe(['--formatter=json', '--no-progress', '--report-skipped']);
});

it('accepts analyse as a pseudo subcommand without passing it to deptrac v4', function (): void {
    $project = FixtureProject::create();
    $adapter = new DeptracToolAdapter();
    $context = $adapter->context(new CliArguments('deptrac', ['analyse', '--formatter=json']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('deptrac', $project->path('vendor/bin/deptrac'), 'vendor/bin/deptrac', 'relative'),
        context: $context,
        config: new ToolConfig('deptrac', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--no-progress', '--report-skipped', '--formatter=json']);
});

it('rejects deptrac commands outside analyse', function (): void {
    $project = FixtureProject::create();
    $adapter = new DeptracToolAdapter();
    $context = $adapter->context(new CliArguments('deptrac', ['init']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('deptrac', $project->path('vendor/bin/deptrac'), 'vendor/bin/deptrac', 'relative'),
        context: $context,
        config: new ToolConfig('deptrac', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Deptrac adapter supports only the "analyse" command.');
});

it('rejects deptrac output files because json must remain on stdout', function (): void {
    $project = FixtureProject::create();
    $adapter = new DeptracToolAdapter();
    $context = $adapter->context(new CliArguments('deptrac', ['--output=build/deptrac.json']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('deptrac', $project->path('vendor/bin/deptrac'), 'vendor/bin/deptrac', 'relative'),
        context: $context,
        config: new ToolConfig('deptrac', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Deptrac adapter requires JSON on stdout.');
});

it('parses deptrac violations as failed status', function (): void {
    $project = FixtureProject::create();
    $adapter = new DeptracToolAdapter();
    $context = $adapter->context(new CliArguments('deptrac'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, json_encode(deptracToolAdapterJsonReport(violations: 1), JSON_THROW_ON_ERROR), '', 0.12),
        context: $context,
        command: deptracPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('deptrac');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['violations' => 1]);
});

it('parses deptrac report errors as error status', function (): void {
    $project = FixtureProject::create();
    $adapter = new DeptracToolAdapter();
    $context = $adapter->context(new CliArguments('deptrac'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, json_encode(deptracToolAdapterJsonReport(errors: 1), JSON_THROW_ON_ERROR), '', 0.12),
        context: $context,
        command: deptracPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

it('returns unsupported tool version when deptrac lacks json formatter', function (): void {
    $project = FixtureProject::create();
    $adapter = new DeptracToolAdapter();
    $context = $adapter->context(new CliArguments('deptrac'), $project->root());

    try {
        $adapter->parse(
            execution: ExecutionResult::completed(1, 'Output formatter json not found.', 'Available formatters: ["table"]', 0.12),
            context: $context,
            command: deptracPreparedCommand($project),
        );
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode()->value)->toBe('unsupported_tool_version');

        return;
    }

    throw new RuntimeException('Expected unsupported tool version exception.');
});

function deptracPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'deptrac',
        binary: $project->path('vendor/bin/deptrac'),
        arguments: ['--formatter=json', '--no-progress', '--report-skipped'],
        cwd: $project->root(),
    );
}

/**
 * @return array<string, mixed>
 */
function deptracToolAdapterJsonReport(int $violations = 0, int $errors = 0): array
{
    return [
        'Report' => [
            'Violations' => $violations,
            'Skipped violations' => 0,
            'Uncovered' => 0,
            'Allowed' => 2,
            'Warnings' => 0,
            'Errors' => $errors,
        ],
        'files' => [],
    ];
}
