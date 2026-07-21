<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\ParallelLint\ParallelLintToolAdapter;
use Tests\Support\FixtureProject;

it('describes parallel-lint discovery metadata', function (): void {
    $definition = (new ParallelLintToolAdapter())->definition();

    expect($definition->name())->toBe('parallel-lint');
    expect($definition->description())->toBe('PHP Parallel Lint syntax checker.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/parallel-lint.bat', 'vendor/bin/parallel-lint', 'parallel-lint']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev php-parallel-lint/php-parallel-lint');
    expect($definition->defaultContext())->toBe('syntax');
});

it('prepares parallel-lint with json default target', function (): void {
    $project = FixtureProject::create();
    $adapter = new ParallelLintToolAdapter();
    $context = $adapter->context(new CliArguments('parallel-lint'), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('parallel-lint', $project->path('vendor/bin/parallel-lint'), 'vendor/bin/parallel-lint', 'relative'),
        context: $context,
        config: new ToolConfig('parallel-lint', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['.', '--json', '--no-progress', '--no-colors']);
});

it('preserves explicit parallel-lint target and json output', function (): void {
    $project = FixtureProject::create();
    $adapter = new ParallelLintToolAdapter();
    $context = $adapter->context(new CliArguments('parallel-lint', ['--json', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('parallel-lint', $project->path('vendor/bin/parallel-lint'), 'vendor/bin/parallel-lint', 'relative'),
        context: $context,
        config: new ToolConfig('parallel-lint', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--no-progress', '--no-colors', '--json', 'src']);
});

it('forces parallel-lint progress and colors off outside raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new ParallelLintToolAdapter();
    $context = $adapter->context(new CliArguments('parallel-lint', ['--colors', '--json', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('parallel-lint', $project->path('vendor/bin/parallel-lint'), 'vendor/bin/parallel-lint', 'relative'),
        context: $context,
        config: new ToolConfig('parallel-lint', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--no-progress', '--no-colors', '--json', 'src']);
});

it('rejects parallel-lint non-json report formats outside raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new ParallelLintToolAdapter();
    $context = $adapter->context(new CliArguments('parallel-lint', ['--checkstyle']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('parallel-lint', $project->path('vendor/bin/parallel-lint'), 'vendor/bin/parallel-lint', 'relative'),
        context: $context,
        config: new ToolConfig('parallel-lint', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'Parallel Lint adapter requires JSON output outside raw mode.');
});

it('parses parallel-lint syntax errors as failed status', function (): void {
    $project = FixtureProject::create();
    $broken = $project->write('src/Broken.php', '<?php echo ;');
    $adapter = new ParallelLintToolAdapter();
    $context = $adapter->context(new CliArguments('parallel-lint'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, parallelLintJson($broken), '', 0.12),
        context: $context,
        command: parallelLintPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('parallel-lint');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['errors' => 1]);
});

it('treats unexpected non-zero parallel-lint exits without errors as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new ParallelLintToolAdapter();
    $context = $adapter->context(new CliArguments('parallel-lint'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, parallelLintJson(), '', 0.12),
        context: $context,
        command: parallelLintPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function parallelLintPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'parallel-lint',
        binary: $project->path('vendor/bin/parallel-lint'),
        arguments: ['.', '--json', '--no-progress', '--no-colors'],
        cwd: $project->root(),
    );
}

function parallelLintJson(?string $broken = null): string
{
    return json_encode([
        'phpVersion' => 80506,
        'hhvmVersion' => '',
        'parallelJobs' => 10,
        'results' => [
            'checkedFiles' => [],
            'filesWithSyntaxError' => $broken === null ? [] : [$broken],
            'skippedFiles' => [],
            'errors' => $broken === null ? [] : [
                [
                    'type' => 'syntaxError',
                    'file' => $broken,
                    'line' => 1,
                    'message' => 'Parse error: syntax error, unexpected token ";" in Broken.php on line 1',
                    'normalizeMessage' => 'Unexpected token ";".',
                    'blame' => null,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
