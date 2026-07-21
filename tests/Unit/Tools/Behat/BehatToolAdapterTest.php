<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\Behat\BehatToolAdapter;
use Sift\Tools\CliArguments;
use Tests\Support\FixtureProject;

it('describes behat discovery metadata', function (): void {
    $definition = (new BehatToolAdapter())->definition();

    expect($definition->name())->toBe('behat');
    expect($definition->description())->toBe('Behat behavior-driven test runner.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/behat.bat', 'vendor/bin/behat', 'behat']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev behat/behat');
    expect($definition->defaultContext())->toBe('test');
});

it('prepares behat with a generated json report', function (): void {
    $project = FixtureProject::create();
    $adapter = new BehatToolAdapter(new TempFileFactory($project->path('build/tmp')));
    $context = $adapter->context(new CliArguments('behat', ['--name=Checkout']), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('behat', $project->path('vendor/bin/behat'), 'vendor/bin/behat', 'relative'),
        context: $context,
        config: new ToolConfig('behat', true, null, [], 120),
    );

    expect($context->filter())->toBe('Checkout');
    expect($command->arguments())->toContain('--format=json', '--no-colors', '--name=Checkout');
    expect($command->artifacts())->toHaveKey('behat_json');
    expect($command->arguments())->toContain('--out=' . $command->artifacts()['behat_json']);
    expect($command->temporaryFiles())->toBe([$command->artifacts()['behat_json']]);
});

it('normalizes behat scenarios and failures', function (): void {
    $project = FixtureProject::create();
    $adapter = new BehatToolAdapter(new TempFileFactory($project->path('build/tmp')));
    $context = $adapter->context(new CliArguments('behat'), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('behat', $project->path('vendor/bin/behat'), 'vendor/bin/behat', 'relative'),
        context: $context,
        config: new ToolConfig('behat', true, null, [], 120),
    );

    file_put_contents($command->artifacts()['behat_json'], json_encode([
        'tests' => 2,
        'skipped' => 0,
        'failed' => 1,
        'pending' => 0,
        'undefined' => 0,
        'time' => 0.25,
        'suites' => [[
            'name' => 'default',
            'features' => [[
                'name' => 'Checkout',
                'file' => $project->path('features/checkout.feature'),
                'scenarios' => [[
                    'name' => 'Successful checkout',
                    'status' => 'passed',
                    'file' => $project->path('features/checkout.feature'),
                    'line' => 4,
                ], [
                    'name' => 'Declined checkout',
                    'status' => 'failed',
                    'file' => $project->path('features/checkout.feature'),
                    'line' => 10,
                    'failures' => [[
                        'message' => 'Then payment is declined: Expected decline.',
                        'type' => 'failed',
                    ]],
                ]],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, '', '', 0.25),
        context: $context,
        command: $command,
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray([
        'tests' => 2,
        'passed' => 1,
        'failures' => 1,
        'errors' => 0,
        'skipped' => 0,
        'duration_seconds' => 0.25,
    ]);
    expect($payload['items'])->toBe([[
        'type' => 'test_failure',
        'test' => 'Declined checkout',
        'suite' => 'default',
        'feature' => 'Checkout',
        'file' => 'features/checkout.feature',
        'line' => 10,
        'message' => 'Then payment is declined: Expected decline.',
        'failure_type' => 'failed',
    ]]);
    expect(is_file($command->artifacts()['behat_json']))->toBeFalse();
});
