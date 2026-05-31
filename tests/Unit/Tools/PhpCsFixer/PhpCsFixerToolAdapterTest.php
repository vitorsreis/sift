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
use Sift\Tools\PhpCsFixer\PhpCsFixerToolAdapter;
use Tests\Support\FixtureProject;

it('describes php-cs-fixer discovery metadata', function (): void {
    $definition = (new PhpCsFixerToolAdapter())->definition();

    expect($definition->name())->toBe('php-cs-fixer');
    expect($definition->description())->toBe('PHP CS Fixer code style fixer.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/php-cs-fixer.bat', 'vendor/bin/php-cs-fixer', 'php-cs-fixer']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev friendsofphp/php-cs-fixer');
    expect($definition->defaultContext())->toBe('style');
    expect($definition->mutationPolicy())->toBe(MutationPolicy::RepairFlag);
    expect($definition->repairCommand())->toBe(['--repair']);
});

it('prepares php-cs-fixer dry-run json defaults', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpCsFixerToolAdapter();
    $context = $adapter->context(new CliArguments('php-cs-fixer', ['src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('php-cs-fixer', $project->path('vendor/bin/php-cs-fixer'), 'vendor/bin/php-cs-fixer', 'relative'),
        context: $context,
        config: new ToolConfig('php-cs-fixer', true, null, [], 120),
    );

    expect($context->repair())->toBeFalse();
    expect($command->arguments())->toBe(['fix', '--dry-run', '--format=json', '--using-cache=no', '--diff', '-v', 'src']);
});

it('prepares php-cs-fixer repair without dry-run when repair is explicit', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpCsFixerToolAdapter();
    $context = $adapter->context(new CliArguments('php-cs-fixer', ['--repair', 'src']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('php-cs-fixer', $project->path('vendor/bin/php-cs-fixer'), 'vendor/bin/php-cs-fixer', 'relative'),
        context: $context,
        config: new ToolConfig('php-cs-fixer', true, null, [], 120),
    );

    expect($context->repair())->toBeTrue();
    expect($command->arguments())->toBe(['fix', '--format=json', '--using-cache=no', '--diff', '-v', 'src']);
});

it('preserves explicit php-cs-fixer fix command and json options', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpCsFixerToolAdapter();
    $context = $adapter->context(new CliArguments('php-cs-fixer', ['fix', '--dry-run', '--format=json', '--using-cache=no']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('php-cs-fixer', $project->path('vendor/bin/php-cs-fixer'), 'vendor/bin/php-cs-fixer', 'relative'),
        context: $context,
        config: new ToolConfig('php-cs-fixer', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['fix', '--diff', '-v', '--dry-run', '--format=json', '--using-cache=no']);
});

it('rejects php-cs-fixer commands outside fix', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpCsFixerToolAdapter();
    $context = $adapter->context(new CliArguments('php-cs-fixer', ['list']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('php-cs-fixer', $project->path('vendor/bin/php-cs-fixer'), 'vendor/bin/php-cs-fixer', 'relative'),
        context: $context,
        config: new ToolConfig('php-cs-fixer', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'PHP CS Fixer adapter supports only the "fix" command.');
});

it('parses php-cs-fixer dry-run changes as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PhpCsFixerToolAdapter();
    $context = $adapter->context(new CliArguments('php-cs-fixer'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(8, phpCsFixerJson($source), '', 0.12),
        context: $context,
        command: phpCsFixerPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('php-cs-fixer');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['files' => 1, 'fixers' => 1]);
});

it('parses php-cs-fixer repair changes as changed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PhpCsFixerToolAdapter();
    $context = $adapter->context(new CliArguments('php-cs-fixer', ['--repair']), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(0, phpCsFixerJson($source), '', 0.12),
        context: $context,
        command: phpCsFixerPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Changed->value);
});

it('treats unexpected non-zero php-cs-fixer exits without files as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpCsFixerToolAdapter();
    $context = $adapter->context(new CliArguments('php-cs-fixer'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(16, phpCsFixerJson(null), '', 0.12),
        context: $context,
        command: phpCsFixerPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function phpCsFixerPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'php-cs-fixer',
        binary: $project->path('vendor/bin/php-cs-fixer'),
        arguments: ['fix', '--dry-run', '--format=json', '--using-cache=no', '--diff', '-v'],
        cwd: $project->root(),
    );
}

function phpCsFixerJson(?string $source): string
{
    return json_encode([
        'about' => 'PHP CS Fixer 3.95.3',
        'files' => $source === null ? [] : [
            [
                'name' => $source,
                'appliedFixers' => ['ordered_imports'],
            ],
        ],
        'time' => ['total' => 0.1],
        'memory' => 8.0,
    ], JSON_THROW_ON_ERROR);
}
