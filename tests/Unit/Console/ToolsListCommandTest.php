<?php

declare(strict_types=1);

use Sift\Console\CommandRoute;
use Sift\Console\Commands\CommandHandler;
use Sift\Console\Commands\ToolsListCommand;
use Sift\Console\OutputFormat;
use Sift\Console\OutputPreferences;
use Sift\Console\OutputSize;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Output\TerminalRenderer;
use Sift\Registry\ToolRegistry;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\ToolContext;
use Sift\Tools\ToolInspector;
use Tests\Support\FixtureProject;

it('lists every supported tool with installed and enabled state', function (): void {
    $project = FixtureProject::create();
    $command = new ToolsListCommand(
        toolInspector: new ToolInspector(new ToolRegistry(
            toolsListCommandAdapter('installed', [PHP_BINARY]),
            toolsListCommandAdapter('missing', ['missing-tool-binary']),
        )),
    );

    $payload = $command->handle(new CommandRoute('tools.list'), $project->root());
    $items = $payload['items'] ?? null;

    if (! is_array($items) || ! is_array($items[0] ?? null) || ! is_array($items[1] ?? null)) {
        throw new RuntimeException('Expected tools list items.');
    }

    expect($command)->toBeInstanceOf(CommandHandler::class);
    expect($payload['tool'])->toBe('sift');
    expect($payload['status'])->toBe('passed');
    expect($payload['summary'])->toBe([
        'supported' => 2,
        'installed' => 1,
        'enabled' => 2,
    ]);
    expect($items[0])->toMatchArray([
        'tool' => 'installed',
        'enabled' => true,
        'installed' => true,
        'status' => 'ON',
    ]);
    expect($items[1])->toMatchArray([
        'tool' => 'missing',
        'enabled' => true,
        'installed' => false,
        'status' => 'OFF',
    ]);
    expect($payload['meta'])->toBe(['subcommand' => 'tools list']);
});

it('streams terminal tools list lines while building the payload', function (): void {
    $project = FixtureProject::create();
    $command = new ToolsListCommand(
        toolInspector: new ToolInspector(new ToolRegistry(
            toolsListCommandAdapter('installed', [PHP_BINARY]),
            toolsListCommandAdapter('missing', ['missing-tool-binary']),
        )),
    );
    $chunks = [];

    $payload = $command->streamTerminal(
        new CommandRoute('tools.list'),
        $project->root(),
        static function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        },
        new TerminalRenderer(),
    );

    expect($payload['summary'])->toMatchArray(['supported' => 2, 'installed' => 1]);
    expect($chunks)->toHaveCount(3);
    expect($chunks[0])->toContain('Supported tools and local availability.');
    expect($chunks[1])->toContain('OK');
    expect($chunks[2])->toContain('NO');
});

it('streams terminal tools list without ansi when colors are disabled', function (): void {
    $project = FixtureProject::create();
    $command = new ToolsListCommand(
        toolInspector: new ToolInspector(new ToolRegistry(
            toolsListCommandAdapter('installed', [PHP_BINARY]),
        )),
    );
    $chunks = [];

    $command->streamTerminal(
        new CommandRoute('tools.list'),
        $project->root(),
        static function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        },
        new TerminalRenderer(),
        new OutputPreferences(OutputSize::Compact, false, false, false, OutputFormat::Terminal, false),
    );

    expect(implode('', $chunks))->not->toContain("\033[");
    expect(implode('', $chunks))->toContain('OK Installed');
});

/**
 * @param non-empty-list<string> $candidates
 */
function toolsListCommandAdapter(string $name, array $candidates): AbstractCliToolAdapter
{
    return new readonly class ($name, $candidates) extends AbstractCliToolAdapter {
        /**
         * @param non-empty-list<string> $candidates
         */
        public function __construct(
            private string $name,
            private array $candidates,
        ) {}

        protected function name(): string
        {
            return $this->name;
        }

        protected function description(): string
        {
            return 'Test tool.';
        }

        protected function binaryCandidates(): array
        {
            return $this->candidates;
        }

        protected function installHint(): string
        {
            return 'Install test tool.';
        }

        protected function defaultContext(): string
        {
            return 'test';
        }

        public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
        {
            return NormalizedResult::passed($context->toolName());
        }
    };
}
