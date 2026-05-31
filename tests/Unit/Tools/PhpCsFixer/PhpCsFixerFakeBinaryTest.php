<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\NormalizedResult;
use Sift\Core\RunStatus;
use Sift\Registry\ToolRegistry;
use Sift\Safety\MachineOutputPolicy;
use Sift\Safety\PolicyPipeline;
use Sift\Tools\CliArguments;
use Sift\Tools\PhpCsFixer\PhpCsFixerToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs php-cs-fixer through a fake binary with dry-run json output', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'php-cs-fixer',
        stdout: phpCsFixerFakeBinaryJson($source),
        exitCode: 8,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new PhpCsFixerToolAdapter()),
        policyPipeline: new PolicyPipeline([new MachineOutputPolicy()]),
    );

    $result = $runner->run(
        arguments: new CliArguments('php-cs-fixer', ['src']),
        config: phpCsFixerFakeBinaryConfig(new ToolConfig('php-cs-fixer', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('php-cs-fixer');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['files' => 1, 'fixers' => 1]);
    expect($fake->argv())->toBe(['fix', '--dry-run', '--format=json', '--using-cache=no', '--diff', '-v', 'src']);
});

function phpCsFixerFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
{
    $indexedTools = [];

    foreach ($tools as $tool) {
        $indexedTools[$tool->name()] = $tool;
    }

    return new SiftConfig(
        schema: 'https://raw.githubusercontent.com/vitorsreis/sift/v2.0.0/resources/schema.json',
        configPath: null,
        usingDefaults: true,
        history: new HistoryConfig(false, '.sift/history', 50, 30, 1048576, true),
        output: new OutputConfig('compact', false, false),
        tools: $indexedTools,
    );
}

function phpCsFixerFakeBinaryJson(string $source): string
{
    return json_encode([
        'about' => 'PHP CS Fixer 3.95.3',
        'files' => [
            [
                'name' => $source,
                'appliedFixers' => ['ordered_imports'],
            ],
        ],
        'time' => ['total' => 0.1],
        'memory' => 8.0,
    ], JSON_THROW_ON_ERROR);
}
