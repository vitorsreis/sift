<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\MutationPolicy;
use Sift\Tools\PhpCs\PhpcbfToolAdapter;
use Tests\Support\FixtureProject;

it('describes phpcbf discovery metadata', function (): void {
    $definition = (new PhpcbfToolAdapter())->definition();

    expect($definition->name())->toBe('phpcbf');
    expect($definition->description())->toBe('PHP_CodeSniffer code fixer.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/phpcbf.bat', 'vendor/bin/phpcbf', 'phpcbf']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev squizlabs/php_codesniffer');
    expect($definition->defaultContext())->toBe('style');
    expect($definition->mutationPolicy())->toBe(MutationPolicy::RepairFlag);
    expect($definition->repairCommand())->toBe(['--repair']);
});

it('requires explicit repair because phpcbf mutates files', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpcbfToolAdapter();
    $context = $adapter->context(new CliArguments('phpcbf', ['src']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('phpcbf', $project->path('vendor/bin/phpcbf'), 'vendor/bin/phpcbf', 'relative'),
        context: $context,
        config: new ToolConfig('phpcbf', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'PHPCBF modifies files; pass --repair to run it.');
});

it('prepares phpcbf repair with quiet no color defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpcbfToolAdapter();
    $context = $adapter->context(new CliArguments('phpcbf', ['--repair', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('phpcbf', $project->path('vendor/bin/phpcbf'), 'vendor/bin/phpcbf', 'relative'),
        context: $context,
        config: new ToolConfig('phpcbf', true, null, [], 120),
    );

    expect($context->repair())->toBeTrue();
    expect($command->arguments())->toBe(['-q', '--no-colors', '--report-width=500', 'src']);
});

it('parses phpcbf fixed files as changed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PhpcbfToolAdapter();
    $context = $adapter->context(new CliArguments('phpcbf', ['--repair']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, phpcbfSummary($source, fixed: 3, remaining: 0), '', 0.12),
        context: $context,
        command: phpcbfPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('phpcbf');
    expect($payload['status'])->toBe(RunStatus::Changed->value);
    expect($payload['summary'])->toMatchArray([
        'result' => 'fixed',
        'files' => 1,
        'fixed' => 3,
        'remaining' => 0,
    ]);
    expect($payload['items'])->toBe([
        [
            'type' => 'changed_file',
            'file' => 'src/Checkout.php',
            'fixed' => 3,
            'remaining' => 0,
        ],
    ]);
});

it('parses phpcbf remaining violations as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PhpcbfToolAdapter();
    $context = $adapter->context(new CliArguments('phpcbf', ['--repair']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(2, phpcbfSummary($source, fixed: 9, remaining: 2), '', 0.12),
        context: $context,
        command: phpcbfPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray([
        'result' => 'remaining',
        'files' => 1,
        'fixed' => 9,
        'remaining' => 2,
    ]);
});

it('parses phpcbf no violations as passed status', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpcbfToolAdapter();
    $context = $adapter->context(new CliArguments('phpcbf', ['--repair']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, PHP_EOL . 'No violations were found' . PHP_EOL, '', 0.12),
        context: $context,
        command: phpcbfPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Passed->value);
    expect($payload['summary'])->toMatchArray([
        'result' => 'passed',
        'files' => 0,
        'fixed' => 0,
        'remaining' => 0,
    ]);
});

it('treats unexpected non-zero phpcbf exits without parsed fixes as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpcbfToolAdapter();
    $context = $adapter->context(new CliArguments('phpcbf', ['--repair']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, 'Unexpected output', '', 0.12),
        context: $context,
        command: phpcbfPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function phpcbfPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'phpcbf',
        binary: $project->path('vendor/bin/phpcbf'),
        arguments: ['-q', '--no-colors', '--report-width=500'],
        cwd: $project->root(),
    );
}

function phpcbfSummary(string $source, int $fixed, int $remaining): string
{
    return implode(PHP_EOL, [
        '',
        'PHPCBF RESULT SUMMARY',
        '--------------------------------------------------------------------------------',
        'FILE                                                            FIXED  REMAINING',
        '--------------------------------------------------------------------------------',
        sprintf('%s  %d      %d', $source, $fixed, $remaining),
        '--------------------------------------------------------------------------------',
        sprintf('A TOTAL OF %d ERRORS WERE FIXED IN 1 FILE', $fixed),
        '--------------------------------------------------------------------------------',
        '',
    ]);
}
