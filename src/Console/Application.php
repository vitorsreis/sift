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
        private ?CliParser $parser = null,
        private ?CommandRouter $router = null,
    ) {}

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        try {
            $route = $this->router()->route($this->parser()->parse(array_slice($argv, 1)));
        } catch (InvalidUsageException $invalidUsageException) {
            return $this->renderUsageError($invalidUsageException);
        }

        return match ($route->handler()) {
            'help' => $this->renderPassed((new HelpCommand())->handle()),
            'version' => $this->renderPassed((new VersionCommand())->handle()),
            default => $this->renderNotImplemented($route),
        };
    }

    private function parser(): CliParser
    {
        return $this->parser ?? CliParser::forSift();
    }

    private function router(): CommandRouter
    {
        return $this->router ?? CommandRouter::forSift();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPassed(array $payload): int
    {
        fwrite(STDOUT, $this->renderer->render($payload));

        return 0;
    }

    private function renderUsageError(InvalidUsageException $exception): int
    {
        fwrite(STDERR, $this->renderer->render([
            'status' => 'error',
            'error' => [
                'code' => 'invalid_usage',
                'message' => $exception->getMessage(),
                'hint' => 'Run "sift help" to list available commands.',
            ],
        ]));

        return 3;
    }

    private function renderNotImplemented(CommandRoute $route): int
    {
        fwrite(STDERR, $this->renderer->render([
            'status' => 'error',
            'error' => [
                'code' => 'invalid_usage',
                'message' => sprintf('Command "%s" is not implemented yet.', $route->handler()),
                'hint' => 'Run "sift help" to list available commands.',
            ],
        ]));

        return 3;
    }
}
