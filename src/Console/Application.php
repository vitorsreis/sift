<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Config\ConfigValidationException;
use Sift\Console\Commands\HelpCommand;
use Sift\Console\Commands\InitCommand;
use Sift\Console\Commands\ValidateCommand;
use Sift\Console\Commands\VersionCommand;
use Sift\Core\ErrorCode;
use Sift\Core\RunStatus;
use Sift\Output\JsonRenderer;

final readonly class Application
{
    public function __construct(
        private JsonRenderer $renderer = new JsonRenderer(),
        private ?CliParser $parser = null,
        private ?CommandRouter $router = null,
        private ?OutputPreferencesResolver $outputPreferencesResolver = null,
    ) {}

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        try {
            $route = $this->router()->route($this->parser()->parse(array_slice($argv, 1)));
            $preferences = $this->outputPreferencesResolver()->resolve($route);
        } catch (InvalidUsageException $invalidUsageException) {
            return $this->renderUsageError($invalidUsageException, $this->outputPreferencesResolver()->defaults());
        }

        try {
            return match ($route->handler()) {
                'help' => $this->renderPassed((new HelpCommand())->handle($route, $this->cwd()), $preferences),
                'version' => $this->renderPassed((new VersionCommand())->handle($route, $this->cwd()), $preferences),
                'init' => $this->renderPassed((new InitCommand())->handle($route, $this->cwd()), $preferences),
                'validate' => $this->renderPassed((new ValidateCommand())->handle($route, $this->cwd()), $preferences),
                default => $this->renderNotImplemented($route, $preferences),
            };
        } catch (ConfigValidationException $configValidationException) {
            return $this->renderConfigError($configValidationException, $preferences);
        }
    }

    private function parser(): CliParser
    {
        return $this->parser ?? CliParser::forSift();
    }

    private function router(): CommandRouter
    {
        return $this->router ?? CommandRouter::forSift();
    }

    private function outputPreferencesResolver(): OutputPreferencesResolver
    {
        return $this->outputPreferencesResolver ?? OutputPreferencesResolver::fromEnvironment();
    }

    private function cwd(): string
    {
        $cwd = getcwd();

        return is_string($cwd) ? $cwd : '.';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPassed(array $payload, OutputPreferences $preferences): int
    {
        fwrite(STDOUT, $this->renderer->render($payload, $preferences));

        return ExitCode::Success->value;
    }

    private function renderUsageError(InvalidUsageException $exception, OutputPreferences $preferences): int
    {
        fwrite(STDERR, $this->renderer->render([
            'status' => RunStatus::Error->value,
            'error' => [
                'code' => ErrorCode::InvalidUsage->value,
                'message' => $exception->getMessage(),
                'hint' => 'Run "sift help" to list available commands.',
            ],
        ], $preferences));

        return ExitCode::UserError->value;
    }

    private function renderConfigError(ConfigValidationException $exception, OutputPreferences $preferences): int
    {
        fwrite(STDERR, $this->renderer->render([
            'status' => RunStatus::Error->value,
            'error' => [
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'path' => $exception->path(),
            ],
        ], $preferences));

        return ExitCode::UserError->value;
    }

    private function renderNotImplemented(CommandRoute $route, OutputPreferences $preferences): int
    {
        fwrite(STDERR, $this->renderer->render([
            'status' => RunStatus::Error->value,
            'error' => [
                'code' => ErrorCode::InvalidUsage->value,
                'message' => sprintf('Command "%s" is not implemented yet.', $route->handler()),
                'hint' => 'Run "sift help" to list available commands.',
            ],
        ], $preferences));

        return ExitCode::UserError->value;
    }
}
