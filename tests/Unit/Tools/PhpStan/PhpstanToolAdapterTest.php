<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\PhpStan\PhpstanToolAdapter;
use Tests\Support\FixtureProject;

it('describes phpstan discovery metadata', function (): void {
    $definition = (new PhpstanToolAdapter())->definition();

    expect($definition->name())->toBe('phpstan');
    expect($definition->description())->toBe('PHPStan static analyser.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/phpstan.bat', 'vendor/bin/phpstan', 'phpstan']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev phpstan/phpstan');
    expect($definition->defaultContext())->toBe('analysis');
});

it('prepares phpstan with safe json defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpstanToolAdapter();
    $context = $adapter->context(new CliArguments('phpstan', ['src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('phpstan', $project->path('vendor/bin/phpstan'), 'vendor/bin/phpstan', 'relative'),
        context: $context,
        config: new ToolConfig('phpstan', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['analyse', '--error-format=json', '--no-progress', '--no-ansi', '--no-interaction', 'src']);
});

it('keeps phpstan machine output controls out of raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpstanToolAdapter();
    $context = $adapter->context(
        new CliArguments('phpstan', ['analyse', 'src'], ['raw' => true]),
        $project->root(),
    );

    $command = $adapter->prepare(
        tool: new LocatedTool('phpstan', $project->path('vendor/bin/phpstan'), 'vendor/bin/phpstan', 'relative'),
        context: $context,
        config: new ToolConfig('phpstan', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['analyse', 'src']);
});

it('keeps an explicit phpstan analyse command without duplicating it', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpstanToolAdapter();
    $context = $adapter->context(new CliArguments('phpstan', ['analyse', '--error-format=json', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('phpstan', $project->path('vendor/bin/phpstan'), 'vendor/bin/phpstan', 'relative'),
        context: $context,
        config: new ToolConfig('phpstan', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['analyse', '--no-progress', '--no-ansi', '--no-interaction', '--error-format=json', 'src']);
});

it('parses phpstan findings as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PhpstanToolAdapter();
    $context = $adapter->context(new CliArguments('phpstan'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, phpstanJson($source, fileErrors: 1), '', 0.12),
        context: $context,
        command: phpstanPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('phpstan');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['file_errors' => 1, 'messages' => 1]);
});

it('treats unexpected non-zero phpstan exits without findings as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpstanToolAdapter();
    $context = $adapter->context(new CliArguments('phpstan'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, phpstanJson(null, fileErrors: 0), '', 0.12),
        context: $context,
        command: phpstanPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function phpstanPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'phpstan',
        binary: $project->path('vendor/bin/phpstan'),
        arguments: ['analyse', '--error-format=json', '--no-progress'],
        cwd: $project->root(),
    );
}

function phpstanJson(?string $source, int $fileErrors): string
{
    $files = [];

    if ($source !== null) {
        $files[$source] = [
            'errors' => $fileErrors,
            'messages' => [
                [
                    'message' => 'Undefined variable $total.',
                    'line' => 9,
                    'identifier' => 'variable.undefined',
                ],
            ],
        ];
    }

    return json_encode([
        'totals' => [
            'errors' => 0,
            'file_errors' => $fileErrors,
        ],
        'files' => $files,
        'errors' => [],
    ], JSON_THROW_ON_ERROR);
}
