<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\Mago\MagoToolAdapter;
use Tests\Support\FixtureProject;

it('describes mago discovery metadata', function (): void {
    $definition = (new MagoToolAdapter())->definition();

    expect($definition->name())->toBe('mago');
    expect($definition->description())->toBe('Mago PHP linter, analyzer, formatter and guard.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/mago.bat', 'vendor/bin/mago', 'mago']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev carthage-software/mago');
    expect($definition->defaultContext())->toBe('quality');
});

it('prepares mago lint with safe json defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new MagoToolAdapter();
    $context = $adapter->context(new CliArguments('mago', ['src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('mago', $project->path('vendor/bin/mago'), 'vendor/bin/mago', 'relative'),
        context: $context,
        config: new ToolConfig('mago', true, null, [], 120),
    );

    expect($context->subcommand())->toBe('lint');
    expect($command->arguments())->toBe(['--colors=never', 'lint', '--reporting-format=json', 'src']);
});

it('preserves mago globals before the normalized subcommand', function (): void {
    $project = FixtureProject::create();
    $adapter = new MagoToolAdapter();
    $context = $adapter->context(
        new CliArguments('mago', ['--workspace', 'app', '--threads=2', 'analyse', '--fix', '--dry-run']),
        $project->root(),
    );

    $command = $adapter->prepare(
        tool: new LocatedTool('mago', $project->path('vendor/bin/mago'), 'vendor/bin/mago', 'relative'),
        context: $context,
        config: new ToolConfig('mago', true, null, [], 120),
    );

    expect($context->subcommand())->toBe('analyze');
    expect($command->arguments())->toBe([
        '--workspace',
        'app',
        '--threads=2',
        '--colors=never',
        'analyze',
        '--reporting-format=json',
        '--fix',
        '--dry-run',
    ]);
});

it('prepares mago format as check mode by default', function (): void {
    $project = FixtureProject::create();
    $adapter = new MagoToolAdapter();
    $context = $adapter->context(new CliArguments('mago', ['fmt', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('mago', $project->path('vendor/bin/mago'), 'vendor/bin/mago', 'relative'),
        context: $context,
        config: new ToolConfig('mago', true, null, [], 120),
    );

    expect($context->subcommand())->toBe('format');
    expect($command->arguments())->toBe(['--colors=never', 'format', '--check', 'src']);
});

it('keeps mago machine output controls out of raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new MagoToolAdapter();
    $context = $adapter->context(
        new CliArguments('mago', ['--colors=always', 'lint', 'src'], ['raw' => true]),
        $project->root(),
    );

    $command = $adapter->prepare(
        tool: new LocatedTool('mago', $project->path('vendor/bin/mago'), 'vendor/bin/mago', 'relative'),
        context: $context,
        config: new ToolConfig('mago', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--colors=always', 'lint', 'src']);
});

it('forces mago colors off outside raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new MagoToolAdapter();
    $context = $adapter->context(new CliArguments('mago', ['--colors=always', 'lint', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('mago', $project->path('vendor/bin/mago'), 'vendor/bin/mago', 'relative'),
        context: $context,
        config: new ToolConfig('mago', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--colors=never', 'lint', '--reporting-format=json', 'src']);
});

it('rejects unsupported mago subcommands', function (): void {
    $project = FixtureProject::create();
    $adapter = new MagoToolAdapter();

    expect(fn(): mixed => $adapter->context(
        new CliArguments('mago', ['init']),
        $project->root(),
    ))->toThrow(InvalidUsageException::class, 'Mago adapter supports only lint, analyze, guard and format.');
});

it('parses mago issue findings as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new MagoToolAdapter();
    $context = $adapter->context(new CliArguments('mago'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, magoIssueJson($source), '', 0.12),
        context: $context,
        command: magoPreparedCommand($project, ['--colors=never', 'lint', '--reporting-format=json']),
    )->toPayload();

    expect($payload['tool'])->toBe('mago');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['issues' => 1]);
});

it('parses mago format changes as failed status', function (): void {
    $project = FixtureProject::create();
    $adapter = new MagoToolAdapter();
    $context = $adapter->context(new CliArguments('mago', ['format']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, "Found 1 file(s) need formatting.", '', 0.12),
        context: $context,
        command: magoPreparedCommand($project, ['--colors=never', 'format', '--check']),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['changed_files' => 1]);
});

/**
 * @param list<string> $arguments
 */
function magoPreparedCommand(FixtureProject $project, array $arguments): PreparedCommand
{
    return new PreparedCommand(
        tool: 'mago',
        binary: $project->path('vendor/bin/mago'),
        arguments: $arguments,
        cwd: $project->root(),
    );
}

function magoIssueJson(string $source): string
{
    return json_encode([
        'issues' => [
            [
                'level' => 'error',
                'code' => 'analysis:invalid-argument',
                'message' => 'Invalid argument.',
                'annotations' => [
                    [
                        'kind' => 'primary',
                        'span' => [
                            'file_id' => [
                                'name' => $source,
                                'path' => $source,
                                'size' => 42,
                                'file_type' => 'host',
                            ],
                            'start' => ['line' => 4, 'offset' => 1],
                            'end' => ['line' => 4, 'offset' => 8],
                        ],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
