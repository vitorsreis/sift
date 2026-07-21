<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\EasyCodingStandard\EasyCodingStandardToolAdapter;
use Sift\Tools\MutationPolicy;
use Tests\Support\FixtureProject;

it('describes easy coding standard discovery metadata', function (): void {
    $definition = (new EasyCodingStandardToolAdapter())->definition();

    expect($definition->name())->toBe('ecs');
    expect($definition->aliases())->toBe(['easy-coding-standard']);
    expect($definition->description())->toBe('Easy Coding Standard style checker and fixer.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/ecs.bat', 'vendor/bin/ecs', 'ecs']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev symplify/easy-coding-standard');
    expect($definition->defaultContext())->toBe('style');
    expect($definition->mutationPolicy())->toBe(MutationPolicy::RepairFlag);
});

it('prepares ecs with json output and without mutation by default', function (): void {
    $project = FixtureProject::create();
    $adapter = new EasyCodingStandardToolAdapter();
    $context = $adapter->context(new CliArguments('ecs', ['check', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('ecs', $project->path('vendor/bin/ecs'), 'vendor/bin/ecs', 'relative'),
        context: $context,
        config: new ToolConfig('ecs', true, null, [], 120),
    );

    expect($context->repair())->toBeFalse();
    expect($command->arguments())->toBe(['--output-format=json', '--no-progress-bar', 'src']);
});

it('prepares ecs fix mode only when repair is explicit', function (): void {
    $project = FixtureProject::create();
    $adapter = new EasyCodingStandardToolAdapter();
    $context = $adapter->context(new CliArguments('ecs', ['--repair', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('ecs', $project->path('vendor/bin/ecs'), 'vendor/bin/ecs', 'relative'),
        context: $context,
        config: new ToolConfig('ecs', true, null, [], 120),
    );

    expect($context->repair())->toBeTrue();
    expect($command->arguments())->toBe(['--fix', '--output-format=json', '--no-progress-bar', 'src']);
});

it('rejects native ecs fix mode without the sift repair flag', function (): void {
    $project = FixtureProject::create();
    $adapter = new EasyCodingStandardToolAdapter();
    $context = $adapter->context(new CliArguments('ecs', ['--fix', 'src']), $project->root());

    expect(fn(): PreparedCommand => $adapter->prepare(
        tool: new LocatedTool('ecs', $project->path('vendor/bin/ecs'), 'vendor/bin/ecs', 'relative'),
        context: $context,
        config: new ToolConfig('ecs', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'ECS modifies files with --fix; pass --repair instead.');
});

it('parses ecs json findings', function (): void {
    $project = FixtureProject::create();
    $adapter = new EasyCodingStandardToolAdapter();
    $context = $adapter->context(new CliArguments('ecs'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(2, easyCodingStandardJson(), '', 0.12),
        context: $context,
        command: easyCodingStandardPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toBe(['errors' => 1, 'diffs' => 1, 'files' => 1]);
    expect($payload['items'][0])->toMatchArray([
        'type' => 'issue',
        'file' => 'src/Checkout.php',
        'line' => 8,
        'message' => 'Expected strict comparison.',
        'rule' => StrictComparisonFixer::class,
    ]);
    expect($payload['items'][1])->toMatchArray(['type' => 'diff', 'file' => 'src/Checkout.php']);
});

it('reports ecs fixes as changed', function (): void {
    $project = FixtureProject::create();
    $adapter = new EasyCodingStandardToolAdapter();
    $context = $adapter->context(new CliArguments('ecs', ['--repair']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, easyCodingStandardJson(errors: 0), '', 0.12),
        context: $context,
        command: easyCodingStandardPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Changed->value);
});

function easyCodingStandardPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'ecs',
        binary: $project->path('vendor/bin/ecs'),
        arguments: ['--output-format=json'],
        cwd: $project->root(),
    );
}

function easyCodingStandardJson(int $errors = 1): string
{
    return json_encode([
        'totals' => ['errors' => $errors, 'diffs' => 1],
        'files' => [
            'src/Checkout.php' => [
                'errors' => [[
                    'line' => 8,
                    'file_path' => 'src/Checkout.php',
                    'message' => 'Expected strict comparison.',
                    'source_class' => StrictComparisonFixer::class,
                ]],
                'diffs' => [[
                    'diff' => "--- Original\n+++ New",
                    'applied_checkers' => [StrictComparisonFixer::class],
                ]],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
