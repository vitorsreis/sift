<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Registry\ToolRegistry;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\ToolContext;
use Sift\Tools\ToolInspector;
use Tests\Support\FixtureProject;

it('inspects every registered tool with installed state and config state', function (): void {
    $project = FixtureProject::create();
    $installed = toolInspectorAdapter(
        name: 'installed',
        candidates: [PHP_BINARY],
        versionCommand: ['-r', 'fwrite(STDOUT, "Installed 1.0\n");'],
    );
    $disabled = toolInspectorAdapter(
        name: 'disabled',
        candidates: [PHP_BINARY],
        versionCommand: ['-r', 'fwrite(STDOUT, "Disabled 1.0\n");'],
    );
    $missing = toolInspectorAdapter(
        name: 'missing',
        candidates: ['missing-tool-binary'],
        versionCommand: ['--version'],
    );

    $items = (new ToolInspector(new ToolRegistry($installed, $disabled, $missing)))->inspect(
        config: toolInspectorConfig(
            new ToolConfig('disabled', false, PHP_BINARY, [], 30),
        ),
        cwd: $project->root(),
    );

    expect($items)->toMatchArray([
        [
            'tool' => 'installed',
            'enabled' => true,
            'installed' => true,
            'status' => 'ON',
            'version' => 'Installed 1.0',
            'path' => PHP_BINARY,
            'configured_binary' => null,
            'install_hint' => 'Install installed.',
        ],
        [
            'tool' => 'disabled',
            'enabled' => false,
            'installed' => true,
            'status' => 'OFF',
            'version' => 'Disabled 1.0',
            'path' => PHP_BINARY,
            'configured_binary' => PHP_BINARY,
            'install_hint' => 'Install disabled.',
        ],
        [
            'tool' => 'missing',
            'enabled' => true,
            'installed' => false,
            'status' => 'OFF',
            'version' => null,
            'path' => null,
            'configured_binary' => null,
            'install_hint' => 'Install missing.',
        ],
    ]);
});

it('caches detected versions for the current inspector instance', function (): void {
    $project = FixtureProject::create();
    $counter = $project->path('counter.txt');
    $code = 'file_put_contents(' . var_export($counter, true) . ', ((int) @file_get_contents(' . var_export($counter, true) . ')) + 1); fwrite(STDOUT, "Cached 1.0");';
    $inspector = new ToolInspector(new ToolRegistry(toolInspectorAdapter(
        name: 'cached',
        candidates: [PHP_BINARY],
        versionCommand: ['-r', $code],
    )));

    $config = toolInspectorConfig();

    expect($inspector->inspect($config, $project->root())[0]['version'])->toBe('Cached 1.0');
    expect($inspector->inspect($config, $project->root())[0]['version'])->toBe('Cached 1.0');
    expect((string) file_get_contents($counter))->toBe('1');
});

/**
 * @param  non-empty-list<string>  $candidates
 * @param  list<string>  $versionCommand
 */
function toolInspectorAdapter(string $name, array $candidates, array $versionCommand): AbstractCliToolAdapter
{
    return new readonly class ($name, $candidates, $versionCommand) extends AbstractCliToolAdapter {
        /**
         * @param  non-empty-list<string>  $candidates
         * @param  list<string>  $versionCommand
         */
        public function __construct(
            private string $name,
            private array $candidates,
            private array $versionCommand,
        ) {}

        protected function name(): string
        {
            return $this->name;
        }

        protected function description(): string
        {
            return 'Inspectable tool.';
        }

        protected function binaryCandidates(): array
        {
            return $this->candidates;
        }

        protected function versionCommand(): array
        {
            return $this->versionCommand;
        }

        protected function installHint(): string
        {
            return 'Install ' . $this->name . '.';
        }

        protected function defaultContext(): string
        {
            return 'inspect';
        }

        public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
        {
            return NormalizedResult::passed($context->toolName());
        }
    };
}

function toolInspectorConfig(ToolConfig ...$tools): SiftConfig
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
