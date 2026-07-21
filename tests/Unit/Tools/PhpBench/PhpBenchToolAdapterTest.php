<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\CliArguments;
use Sift\Tools\PhpBench\PhpBenchToolAdapter;
use Tests\Support\FixtureProject;

it('describes phpbench discovery metadata', function (): void {
    $definition = (new PhpBenchToolAdapter())->definition();

    expect($definition->name())->toBe('phpbench');
    expect($definition->description())->toBe('PHPBench benchmark runner.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/phpbench.bat', 'vendor/bin/phpbench', 'phpbench']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev phpbench/phpbench');
    expect($definition->defaultContext())->toBe('benchmark');
});

it('prepares phpbench run with an xml dump', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpBenchToolAdapter(new TempFileFactory($project->path('build/tmp')));
    $context = $adapter->context(new CliArguments('phpbench', ['run', 'benchmarks']), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('phpbench', $project->path('vendor/bin/phpbench'), 'vendor/bin/phpbench', 'relative'),
        context: $context,
        config: new ToolConfig('phpbench', true, null, [], 120),
    );

    expect($context->subcommand())->toBe('run');
    expect($context->mode())->toBe('benchmark');
    expect($command->arguments()[0])->toBe('run');
    expect($command->arguments())->toContain('benchmarks', '--dump-file=' . $command->artifacts()['phpbench_xml']);
    expect($command->temporaryFiles())->toBe([$command->artifacts()['phpbench_xml']]);
});

it('normalizes phpbench measurements and assertion failures', function (): void {
    $project = FixtureProject::create();
    $adapter = new PhpBenchToolAdapter(new TempFileFactory($project->path('build/tmp')));
    $context = $adapter->context(new CliArguments('phpbench'), $project->root());
    $command = $adapter->prepare(
        tool: new LocatedTool('phpbench', $project->path('vendor/bin/phpbench'), 'vendor/bin/phpbench', 'relative'),
        context: $context,
        config: new ToolConfig('phpbench', true, null, [], 120),
    );

    file_put_contents($command->artifacts()['phpbench_xml'], <<<'XML'
<?xml version="1.0"?>
<phpbench version="1.7.0">
  <suite tag="main">
    <benchmark class="CheckoutBench">
      <subject name="benchCheckout">
        <variant revs="100" output-time-unit="microseconds" output-mode="time">
          <parameter-set name="small"/>
          <failures><failure>Mean time exceeded 10μs.</failure></failures>
          <iteration time-net="12" time-revs="1" time-avg="12"/>
          <stats min="11" max="13" mean="12" mode="12" rstdev="1.5" stdev="0.2"/>
        </variant>
      </subject>
    </benchmark>
  </suite>
</phpbench>
XML);

    $payload = $adapter->parse(
        execution: ExecutionResult::completed(1, '', '', 0.2),
        context: $context,
        command: $command,
    )->toPayload();

    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toBe([
        'benchmarks' => 1,
        'subjects' => 1,
        'variants' => 1,
        'iterations' => 1,
        'failures' => 1,
        'errors' => 0,
    ]);
    expect($payload['items'][0])->toMatchArray([
        'type' => 'benchmark',
        'benchmark' => 'CheckoutBench',
        'subject' => 'benchCheckout',
        'parameter_set' => 'small',
        'iterations' => 1,
        'revolutions' => 100,
        'mean' => 12.0,
        'rstdev' => 1.5,
        'time_unit' => 'microseconds',
        'time_mode' => 'time',
    ]);
    expect($payload['items'][1])->toBe([
        'type' => 'issue',
        'benchmark' => 'CheckoutBench',
        'subject' => 'benchCheckout',
        'parameter_set' => 'small',
        'message' => 'Mean time exceeded 10μs.',
    ]);
    expect(is_file($command->artifacts()['phpbench_xml']))->toBeFalse();
});
