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
use Sift\Tools\Behat\BehatToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\Codeception\CodeceptionToolAdapter;
use Sift\Tools\ComposerNormalize\ComposerNormalizeToolAdapter;
use Sift\Tools\EasyCodingStandard\EasyCodingStandardToolAdapter;
use Sift\Tools\GrumPhp\GrumPhpToolAdapter;
use Sift\Tools\PhpBench\PhpBenchToolAdapter;
use Sift\Tools\Testing\TestRunnerCommandFactory;
use Sift\Tools\ToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs behat through a fake binary with normalized json output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'behat',
        writes: [
            '--out' => json_encode([
                'tests' => 1,
                'skipped' => 0,
                'failed' => 0,
                'pending' => 0,
                'undefined' => 0,
                'time' => 0.1,
                'suites' => [[
                    'name' => 'default',
                    'features' => [[
                        'name' => 'Checkout',
                        'scenarios' => [[
                            'name' => 'Successful checkout',
                            'status' => 'passed',
                            'file' => 'features/checkout.feature',
                            'line' => 3,
                        ]],
                    ]],
                ]],
            ], JSON_THROW_ON_ERROR),
        ],
    );
    $result = runSupportedFakeTool(
        project: $project,
        adapter: new BehatToolAdapter(new TempFileFactory($project->path('build/tmp'))),
        tool: 'behat',
        fake: $fake,
        arguments: ['--name=checkout'],
    );

    expect($result->toPayload()['summary'])->toMatchArray(['tests' => 1, 'passed' => 1]);
    expect($fake->argv())->toContain('--format=json', '--name=checkout');
    expect(implode(' ', $fake->argv()))->toContain('--out=');
});

it('runs codeception through a fake binary with normalized junit output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'codeception',
        writes: [
            '--xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites><testsuite tests="1"><testcase name="checkout succeeds" file="tests/Acceptance/CheckoutCest.php"/></testsuite></testsuites>
XML,
        ],
    );
    $result = runSupportedFakeTool(
        project: $project,
        adapter: new CodeceptionToolAdapter(commandFactory: new TestRunnerCommandFactory(new TempFileFactory($project->path('build/tmp')))),
        tool: 'codeception',
        fake: $fake,
    );

    expect($result->toPayload()['summary'])->toMatchArray(['tests' => 1, 'passed' => 1]);
    expect($fake->argv()[0])->toBe('run');
    expect(implode(' ', $fake->argv()))->toContain('--xml=');
});

it('runs phpbench through a fake binary with normalized measurements', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'phpbench',
        writes: [
            '--dump-file' => <<<'XML'
<?xml version="1.0"?>
<phpbench version="1.7.0"><suite><benchmark class="CheckoutBench"><subject name="benchCheckout"><variant revs="10" output-time-unit="microseconds" output-mode="time"><parameter-set name="default"/><iteration time-net="5"/><stats mean="5" rstdev="0"/></variant></subject></benchmark></suite></phpbench>
XML,
        ],
    );
    $result = runSupportedFakeTool(
        project: $project,
        adapter: new PhpBenchToolAdapter(new TempFileFactory($project->path('build/tmp'))),
        tool: 'phpbench',
        fake: $fake,
    );
    $payload = $result->toPayload();

    expect($payload['status'])->toBe(RunStatus::Passed->value);
    expect($payload['summary'])->toMatchArray(['benchmarks' => 1, 'variants' => 1]);
    expect($payload['items'][0])->toMatchArray(['type' => 'benchmark', 'mean' => 5.0]);
    expect($fake->argv()[0])->toBe('run');
    expect(implode(' ', $fake->argv()))->toContain('--dump-file=');
});

it('runs composer normalize through a fake composer binary in dry-run mode', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'composer-normalize',
        stderr: "composer.json is not normalized.\n--- Original\n+++ Normalized",
        exitCode: 1,
    );
    $result = runSupportedFakeTool(
        project: $project,
        adapter: new ComposerNormalizeToolAdapter(),
        tool: 'composer-normalize',
        fake: $fake,
    );

    expect($result->toPayload()['status'])->toBe(RunStatus::Failed->value);
    expect($fake->argv())->toBe(['normalize', '--no-progress', '--no-ansi', '--no-interaction', '--dry-run', '--diff']);
});

it('runs ecs through a fake binary with normalized json output', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'ecs',
        stdout: json_encode([
            'totals' => ['errors' => 0, 'diffs' => 0],
            'files' => [],
        ], JSON_THROW_ON_ERROR),
    );
    $result = runSupportedFakeTool(
        project: $project,
        adapter: new EasyCodingStandardToolAdapter(),
        tool: 'ecs',
        fake: $fake,
        arguments: ['src'],
    );

    expect($result->toPayload()['status'])->toBe(RunStatus::Passed->value);
    expect($fake->argv())->toBe(['--output-format=json', '--no-progress-bar', 'src']);
});

it('runs grumphp through a fake binary and normalizes task results', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'grumphp',
        stdout: "Running task 1/1: phpunit... ✔",
    );
    $result = runSupportedFakeTool(
        project: $project,
        adapter: new GrumPhpToolAdapter(),
        tool: 'grumphp',
        fake: $fake,
    );

    expect($result->toPayload()['summary'])->toBe(['tasks' => 1, 'passed' => 1, 'failed' => 0]);
    expect($fake->argv())->toBe(['run', '--no-ansi', '--no-interaction']);
});

/**
 * @param list<string> $arguments
 */
function runSupportedFakeTool(
    FixtureProject $project,
    ToolAdapter $adapter,
    string $tool,
    FakeBinary $fake,
    array $arguments = [],
): NormalizedResult {
    $result = (new ToolRunner(new ToolRegistry($adapter)))->run(
        arguments: new CliArguments($tool, $arguments),
        config: new SiftConfig(
            schema: 'https://raw.githubusercontent.com/vitorsreis/sift/master/resources/schema.json',
            configPath: null,
            usingDefaults: true,
            history: new HistoryConfig(false, '.sift/history', 50, 30, 1_048_576, true),
            output: new OutputConfig('compact', false, false),
            tools: [$tool => new ToolConfig($tool, true, $fake->binary(), [], 30)],
        ),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    return $result;
}
