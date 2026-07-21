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
use Sift\Tools\PhpStan\PhpstanToolAdapter;
use Sift\Tools\ToolRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs phpstan through a fake binary with injected json output', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'phpstan',
        stdout: phpstanFakeBinaryJson($source),
        exitCode: 1,
    );
    $runner = new ToolRunner(
        registry: new ToolRegistry(new PhpstanToolAdapter()),
    );

    $result = $runner->run(
        arguments: new CliArguments('phpstan', ['src']),
        config: phpstanFakeBinaryConfig(new ToolConfig('phpstan', true, $fake->binary(), [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('phpstan');
    expect($payload['status'])->toBe(RunStatus::Failed->value);
    expect($payload['summary'])->toMatchArray(['file_errors' => 1, 'messages' => 1]);
    expect(phpstanFakeBinaryCommand($payload))->toBe([
        $fake->binary(),
        'analyse',
        '--error-format=json',
        '--no-progress',
        '--no-ansi',
        '--no-interaction',
        'src',
    ]);
    expect($fake->argv())->toBe(['analyse', '--error-format=json', '--no-progress', '--no-ansi', '--no-interaction', 'src']);
});

function phpstanFakeBinaryConfig(ToolConfig ...$tools): SiftConfig
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

function phpstanFakeBinaryJson(string $source): string
{
    return json_encode([
        'totals' => [
            'errors' => 0,
            'file_errors' => 1,
        ],
        'files' => [
            $source => [
                'errors' => 1,
                'messages' => [
                    [
                        'message' => 'Undefined variable $total.',
                        'line' => 9,
                        'identifier' => 'variable.undefined',
                    ],
                ],
            ],
        ],
        'errors' => [],
    ], JSON_THROW_ON_ERROR);
}

/**
 * @param array{meta: array<string, mixed>} $payload
 * @return list<string>
 */
function phpstanFakeBinaryCommand(array $payload): array
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
