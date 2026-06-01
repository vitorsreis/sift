<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;
use Sift\Filesystem\TempFileFactory;
use Sift\Registry\ToolRegistry;
use Sift\Tools\CliArguments;
use Sift\Tools\Testing\ParatestToolAdapter;
use Sift\Tools\Testing\PestToolAdapter;
use Sift\Tools\Testing\PhpunitToolAdapter;
use Sift\Tools\Testing\TestRunnerCommandFactory;
use Sift\Tools\ToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs phpunit through a fake binary and keeps runner metadata', function (): void {
    $project = FixtureProject::create();
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'phpunit',
        stdout: 'phpunit stdout',
        stderr: 'phpunit stderr',
        exitCode: 1,
        writes: [
            '--log-junit' => junitReport($test, failed: true),
        ],
    );

    $payload = runTestAdapter(
        project: $project,
        adapter: new PhpunitToolAdapter(commandFactory: testRunnerCommandFactory($project)),
        tool: 'phpunit',
        fake: $fake,
        args: ['--filter', 'CheckoutTest'],
    )->toPayload();

    expect($payload['tool'])->toBe('phpunit');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['tests' => 1, 'failures' => 1]);
    expect($payload['items'][0]['file'])->toBe('tests/Feature/CheckoutTest.php');
    expect($payload['meta']['exit_code'])->toBe(1);
    expect($payload['meta']['filter'])->toBe('CheckoutTest');
    expect($payload['meta']['coverage'])->toBeFalse();
    expect(payloadCommand($payload)[0])->toBe($fake->binary());
    expect($fake->argv())->toContain('--filter', 'CheckoutTest', '--log-junit');

    $junitPath = optionValue($fake->argv(), '--log-junit');

    expect($junitPath)->toBeString();
    expect(is_file((string) $junitPath))->toBeFalse();
});

it('runs pest through a fake binary with coverage reports', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'pest',
        writes: [
            '--log-junit' => junitReport($test),
            '--coverage-clover' => cloverReport($source, percent: 70),
        ],
    );

    $payload = runTestAdapter(
        project: $project,
        adapter: new PestToolAdapter(commandFactory: testRunnerCommandFactory($project)),
        tool: 'pest',
        fake: $fake,
        args: ['--filter=CheckoutTest', '--coverage-text', '--min=90'],
    )->toPayload();

    expect($payload['tool'])->toBe('pest');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray([
        'tests' => 1,
        'passed' => 1,
        'coverage_percent' => 70.0,
        'coverage_min' => 90.0,
    ]);
    expect($payload['meta']['filter'])->toBe('CheckoutTest');
    expect($payload['meta']['coverage'])->toBeTrue();
    expect($payload['meta']['coverage_min'])->toBe(90.0);
    expect($fake->argv())->toContain('--coverage-text', '--coverage-clover');
});

it('runs paratest through a fake binary with an explicit junit report path', function (): void {
    $project = FixtureProject::create();
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $junit = 'reports/junit.xml';
    $fake = FakeBinary::create(
        project: $project,
        name: 'paratest',
        writes: [
            '--log-junit' => junitReport($test),
        ],
    );

    $payload = runTestAdapter(
        project: $project,
        adapter: new ParatestToolAdapter(commandFactory: testRunnerCommandFactory($project)),
        tool: 'paratest',
        fake: $fake,
        args: ['--log-junit=' . $junit],
    )->toPayload();

    expect($payload['tool'])->toBe('paratest');
    expect($payload['status'])->toBe(RunStatus::Passed->value);
    expect($payload['summary'])->toMatchArray(['tests' => 1, 'passed' => 1]);
    expect($fake->argv())->toContain('--log-junit=' . $junit);
    expect(is_file($project->path($junit)))->toBeTrue();
});

/**
 * @param list<string> $args
 */
function runTestAdapter(
    FixtureProject $project,
    ToolAdapter $adapter,
    string $tool,
    FakeBinary $fake,
    array $args,
): NormalizedResult {
    $runner = new ToolRunner(
        registry: new ToolRegistry($adapter),
    );

    $result = $runner->run(
        arguments: new CliArguments($tool, $args),
        config: testRunnerConfig(new ToolConfig($tool, true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    return $result;
}

function testRunnerCommandFactory(FixtureProject $project): TestRunnerCommandFactory
{
    return new TestRunnerCommandFactory(new TempFileFactory($project->path('build/tmp')));
}

function testRunnerConfig(ToolConfig ...$tools): SiftConfig
{
    $indexedTools = [];

    foreach ($tools as $tool) {
        $indexedTools[$tool->name()] = $tool;
    }

    return new SiftConfig(
        schema: 'https://raw.githubusercontent.com/vitorsreis/sift/master/resources/schema.json',
        configPath: null,
        usingDefaults: true,
        history: new HistoryConfig(false, '.sift/history', 50, 30, 1048576, true),
        output: new OutputConfig('compact', false, false),
        tools: $indexedTools,
    );
}

/**
 * @param list<string> $arguments
 */
function optionValue(array $arguments, string $option): ?string
{
    foreach ($arguments as $index => $argument) {
        if (str_starts_with($argument, $option . '=')) {
            return substr($argument, strlen($option) + 1);
        }

        if ($argument === $option) {
            return $arguments[$index + 1] ?? null;
        }
    }

    return null;
}

/**
 * @param array{meta: array<string, mixed>} $payload
 * @return list<string>
 */
function payloadCommand(array $payload): array
{
    $command = $payload['meta']['command'] ?? null;

    if (! is_array($command) || ! array_is_list($command)) {
        throw new RuntimeException('Payload command meta must be a list.');
    }

    $arguments = [];

    foreach ($command as $argument) {
        if (! is_string($argument)) {
            throw new RuntimeException('Payload command meta must contain only strings.');
        }

        $arguments[] = $argument;
    }

    return $arguments;
}

function junitReport(string $testFile, bool $failed = false): string
{
    $body = $failed
        ? '<failure message="Expected true">Failed asserting that false is true.</failure>'
        : '';

    return sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite tests="1" failures="%d">
    <testcase name="it checks out" file="%s">%s</testcase>
  </testsuite>
</testsuites>
XML,
        $failed ? 1 : 0,
        htmlspecialchars($testFile, ENT_QUOTES | ENT_XML1),
        $body,
    );
}

function cloverReport(string $sourceFile, int $percent): string
{
    return sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="%s"><metrics statements="100" coveredstatements="%d"/></file>
    <metrics statements="100" coveredstatements="%d"/>
  </project>
</coverage>
XML,
        htmlspecialchars($sourceFile, ENT_QUOTES | ENT_XML1),
        $percent,
        $percent,
    );
}
