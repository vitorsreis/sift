<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\PhpMd\PhpmdToolAdapter;
use Tests\Support\FixtureProject;

it('describes phpmd discovery metadata', function (): void {
    $definition = (new PhpmdToolAdapter())->definition();

    expect($definition->name())->toBe('phpmd');
    expect($definition->description())->toBe('PHPMD static code analyzer.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/phpmd.bat', 'vendor/bin/phpmd', 'phpmd']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev phpmd/phpmd');
    expect($definition->defaultContext())->toBe('quality');
});

it('prepares phpmd with json default target and rulesets', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpmdToolAdapter();
    $context = $adapter->context(new CliArguments('phpmd'), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('phpmd', $project->path('vendor/bin/phpmd'), 'vendor/bin/phpmd', 'relative'),
        context: $context,
        config: new ToolConfig('phpmd', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['.', 'json', 'cleancode,codesize,controversial,design,naming,unusedcode']);
});

it('prepares phpmd with explicit target and custom ruleset', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpmdToolAdapter();
    $context = $adapter->context(new CliArguments('phpmd', ['src', 'phpmd.xml', '--minimumpriority', '2']), $project->root());

    $command = $adapter->prepare(
        tool: new LocatedTool('phpmd', $project->path('vendor/bin/phpmd'), 'vendor/bin/phpmd', 'relative'),
        context: $context,
        config: new ToolConfig('phpmd', true, null, [], 120),
    );

    expect($command->arguments())->toBe(['src', 'json', 'phpmd.xml', '--minimumpriority', '2']);
});

it('rejects phpmd non-json formats outside raw mode', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpmdToolAdapter();
    $context = $adapter->context(new CliArguments('phpmd', ['src', 'text', 'cleancode']), $project->root());

    expect(fn(): mixed => $adapter->prepare(
        tool: new LocatedTool('phpmd', $project->path('vendor/bin/phpmd'), 'vendor/bin/phpmd', 'relative'),
        context: $context,
        config: new ToolConfig('phpmd', true, null, [], 120),
    ))->toThrow(InvalidUsageException::class, 'PHPMD adapter requires the json format outside raw mode.');
});

it('parses phpmd violations as failed status', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $adapter = new PhpmdToolAdapter();
    $context = $adapter->context(new CliArguments('phpmd'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(2, phpmdJson($source), '', 0.12),
        context: $context,
        command: phpmdPreparedCommand($project),
    )->toPayload();

    expect($payload['tool'])->toBe('phpmd');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['violations' => 1]);
});

it('treats unexpected non-zero phpmd exits without violations as errors', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpmdToolAdapter();
    $context = $adapter->context(new CliArguments('phpmd'), $project->root());

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, phpmdJson(null), '', 0.12),
        context: $context,
        command: phpmdPreparedCommand($project),
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Error->value);
});

function phpmdPreparedCommand(FixtureProject $project): PreparedCommand
{
    return new PreparedCommand(
        tool: 'phpmd',
        binary: $project->path('vendor/bin/phpmd'),
        arguments: ['.', 'json', 'cleancode,codesize,controversial,design,naming,unusedcode'],
        cwd: $project->root(),
    );
}

function phpmdJson(?string $source): string
{
    return json_encode([
        'version' => '2.15.0',
        'package' => 'PHPMD',
        'files' => $source === null ? [] : [
            [
                'file' => $source,
                'violations' => [
                    [
                        'beginLine' => 8,
                        'endLine' => 8,
                        'package' => null,
                        'class' => null,
                        'method' => null,
                        'description' => 'Avoid using static access.',
                        'rule' => 'StaticAccess',
                        'ruleSet' => 'Clean Code Rules',
                        'externalInfoUrl' => 'https://phpmd.org/rules/cleancode.html#staticaccess',
                        'priority' => 1,
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
