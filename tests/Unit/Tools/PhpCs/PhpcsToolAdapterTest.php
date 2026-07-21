<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\PhpCs\PhpcsToolAdapter;
use Tests\Support\FixtureProject;

it('describes phpcs discovery metadata', function (): void {
    $definition = (new PhpcsToolAdapter())->definition();

    expect($definition->name())->toBe('phpcs');
    expect($definition->description())->toBe('PHP_CodeSniffer linter.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/phpcs.bat', 'vendor/bin/phpcs', 'phpcs']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev squizlabs/php_codesniffer');
    expect($definition->defaultContext())->toBe('style');
});

it('prepares phpcs with json quiet no color defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpcsToolAdapter();
    $context = $adapter->context(new CliArguments('phpcs', ['src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('phpcs', $project->path('vendor/bin/phpcs'), 'vendor/bin/phpcs', 'relative'),
        context: $context,
        config: new ToolConfig('phpcs', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--report=json', '-q', '--no-colors', 'src']);
});

it('passes phpcs presentation arguments through in raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpcsToolAdapter();
    $context = $adapter->context(
        new CliArguments('phpcs', ['--report=full', '--colors', 'src'], ['raw' => true]),
        $project->root(),
    );

    $command = $adapter->prepare(
        tool: new LocatedTool('phpcs', $project->path('vendor/bin/phpcs'), 'vendor/bin/phpcs', 'relative'),
        context: $context,
        config: new ToolConfig('phpcs', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['--report=full', '--colors', 'src']);
});

it('parses phpcs findings as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PhpcsToolAdapter();
    $context = $adapter->context(new CliArguments('phpcs'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, phpcsJson($source, errors: 1, warnings: 0), '', 0.12),
        context: $context,
        command: phpcsPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('phpcs');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['errors' => 1, 'warnings' => 0, 'messages' => 1]);
});

it('treats unexpected non-zero phpcs exits without findings as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpcsToolAdapter();
    $context = $adapter->context(new CliArguments('phpcs'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, phpcsJson(null, errors: 0, warnings: 0), '', 0.12),
        context: $context,
        command: phpcsPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function phpcsPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'phpcs',
        binary: $project->path('vendor/bin/phpcs'),
        arguments: ['--report=json', '-q', '--no-colors'],
        cwd: $project->root(),
    );
}

function phpcsJson(?string $source, int $errors, int $warnings): string
{
    $files = [];

    if ($source !== null) {
        $files[$source] = [
            'errors' => $errors,
            'warnings' => $warnings,
            'messages' => [
                [
                    'message' => 'Expected 1 space after comma.',
                    'source' => 'Squiz.Functions.FunctionDeclarationArgumentSpacing',
                    'severity' => 5,
                    'fixable' => true,
                    'type' => 'ERROR',
                    'line' => 12,
                    'column' => 27,
                ],
            ],
        ];
    }

    return json_encode([
        'totals' => [
            'errors' => $errors,
            'warnings' => $warnings,
            'fixable' => $source === null ? 0 : 1,
        ],
        'files' => $files,
    ], JSON_THROW_ON_ERROR);
}
