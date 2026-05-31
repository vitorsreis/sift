<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Console\Commands\HelpCommand;
use Sift\Console\Commands\VersionCommand;
use Sift\Output\JsonRenderer;

final readonly class Application
{
    public function __construct(
        private JsonRenderer $renderer = new JsonRenderer(),
    ) {}

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        return match ($command) {
            'help', '--help', '-h' => $this->renderPassed((new HelpCommand())->handle()),
            'version', '--version', '-V' => $this->renderPassed((new VersionCommand())->handle()),
            default => $this->renderError($command),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPassed(array $payload): int
    {
        fwrite(STDOUT, $this->renderer->render($payload));

        return 0;
    }

    private function renderError(string $command): int
    {
        fwrite(STDERR, $this->renderer->render([
            'status' => 'error',
            'error' => [
                'code' => 'invalid_usage',
                'message' => sprintf('Unknown command "%s".', $command),
                'hint' => 'Run "sift help" to list available commands.',
            ],
        ]));

        return 3;
    }
}
