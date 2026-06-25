<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;
use Sift\Registry\ToolRegistry;
use Sift\Tools\CliArguments;
use Sift\Tools\PhpCs\PhpcbfToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs phpcbf through a fake binary only when repair is explicit', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'phpcbf',
        stdout: phpcbfFakeBinarySummary($source),
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new PhpcbfToolAdapter()),
    );

    $result = $runner->run(
        arguments: new CliArguments('phpcbf', ['--repair', 'src']),
        config: phpcbfFakeBinaryConfig(new ToolConfig('phpcbf', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('phpcbf');
    expect($payload['status'])->toBe(RunStatus::Changed->value);
    expect($payload['summary'])->toMatchArray(['result' => 'fixed', 'fixed' => 3, 'remaining' => 0]);
    expect($fake->argv())->toBe(['-q', '--no-colors', '--report-width=500', 'src']);
});

function phpcbfFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function phpcbfFakeBinarySummary(string $source): string
{
    return implode(PHP_EOL, [
        '',
        'PHPCBF RESULT SUMMARY',
        '--------------------------------------------------------------------------------',
        'FILE                                                            FIXED  REMAINING',
        '--------------------------------------------------------------------------------',
        sprintf('%s  3      0', $source),
        '--------------------------------------------------------------------------------',
        'A TOTAL OF 3 ERRORS WERE FIXED IN 1 FILE',
        '--------------------------------------------------------------------------------',
        '',
    ]);
}
