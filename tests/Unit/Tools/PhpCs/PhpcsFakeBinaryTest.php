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
use Sift\Tools\PhpCs\PhpcsToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs phpcs through a fake binary with injected json report', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'phpcs',
        stdout: phpcsFakeBinaryJson($source),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new PhpcsToolAdapter()),
    );

    $result = $runner->run(
        arguments: new CliArguments('phpcs', ['src']),
        config: phpcsFakeBinaryConfig(new ToolConfig('phpcs', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('phpcs');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['errors' => 1, 'warnings' => 0, 'messages' => 1]);
    expect($fake->argv())->toBe(['--report=json', '-q', '--no-colors', 'src']);
});

function phpcsFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function phpcsFakeBinaryJson(string $source): string
{
    return json_encode([
        'totals' => [
            'errors' => 1,
            'warnings' => 0,
            'fixable' => 1,
        ],
        'files' => [
            $source => [
                'errors' => 1,
                'warnings' => 0,
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
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}
