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
use Sift\Exceptions\UserFacingException;
use Sift\Output\ErrorPayload;
use Sift\Output\JsonRenderer;

final readonly class Application
{
    public function __construct(
        private JsonRenderer $renderer = new JsonRenderer(),
        private ?CliParser $parser = null,
        private ?CommandRouter $router = null,
        private ?OutputPreferencesResolver $outputPreferencesResolver = null,
        private ?ExitCodeResolver $exitCodeResolver = null,
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
        } catch (UserFacingException $userFacingException) {
            return $this->renderUserFacingError($userFacingException, $preferences);
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

    private function exitCodeResolver(): ExitCodeResolver
    {
        return $this->exitCodeResolver ?? new ExitCodeResolver();
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
        $status = $payload['status'] ?? RunStatus::Passed->value;
        $runStatus = is_string($status) ? RunStatus::tryFrom($status) : null;

        return $this->exitCodeResolver()->resolve($runStatus ?? RunStatus::Passed)->value;
    }

    private function renderUsageError(InvalidUsageException $exception, OutputPreferences $preferences): int
    {
        fwrite(STDERR, $this->renderer->render(ErrorPayload::fromInvalidUsage($exception), $preferences));

        return $this->exitCodeResolver()->resolve(RunStatus::Error, ErrorCode::InvalidUsage)->value;
    }

    private function renderConfigError(ConfigValidationException $exception, OutputPreferences $preferences): int
    {
        fwrite(STDERR, $this->renderer->render(ErrorPayload::fromConfigValidation($exception), $preferences));
        $errorCode = ErrorCode::tryFrom($exception->errorCode()) ?? ErrorCode::InvalidConfig;

        return $this->exitCodeResolver()->resolve(RunStatus::Error, $errorCode)->value;
    }

    private function renderUserFacingError(UserFacingException $exception, OutputPreferences $preferences): int
    {
        fwrite(STDERR, $this->renderer->render(ErrorPayload::fromUserFacing($exception), $preferences));

        return $this->exitCodeResolver()->resolve(RunStatus::Error, $exception->errorCode())->value;
    }

    private function renderNotImplemented(CommandRoute $route, OutputPreferences $preferences): int
    {
        fwrite(STDERR, $this->renderer->render(ErrorPayload::make(
            errorCode: ErrorCode::InvalidUsage,
            message: sprintf('Command "%s" is not implemented yet.', $route->handler()),
            hint: 'Run "sift help" to list available commands.',
        ), $preferences));

        return $this->exitCodeResolver()->resolve(RunStatus::Error, ErrorCode::InvalidUsage)->value;
    }
}
