<?php

declare(strict_types=1);

namespace Sift\Console;

use Closure;
use Sift\Config\ConfigLoader;
use Sift\Config\ConfigValidationException;
use Sift\Console\Commands\HelpCommand;
use Sift\Console\Commands\HistoryClearCommand;
use Sift\Console\Commands\HistoryListCommand;
use Sift\Console\Commands\HistoryRemoveCommand;
use Sift\Console\Commands\HistoryViewCommand;
use Sift\Console\Commands\InitCommand;
use Sift\Console\Commands\RunToolCommand;
use Sift\Console\Commands\RunToolCommandResult;
use Sift\Console\Commands\SkillsAddCommand;
use Sift\Console\Commands\SkillsFindCommand;
use Sift\Console\Commands\SkillsHelpCommand;
use Sift\Console\Commands\SkillsInitCommand;
use Sift\Console\Commands\SkillsListCommand;
use Sift\Console\Commands\SkillsRemoveCommand;
use Sift\Console\Commands\SkillsUpdateCommand;
use Sift\Console\Commands\ToolsListCommand;
use Sift\Console\Commands\ValidateCommand;
use Sift\Console\Commands\VersionCommand;
use Sift\Core\ErrorCode;
use Sift\Core\RunStatus;
use Sift\Exceptions\UserFacingException;
use Sift\History\SecretRedactor;
use Sift\Output\ErrorPayload;
use Sift\Output\JsonRenderer;
use Sift\Output\TerminalRenderer;
use Sift\Workspace\WorkspaceResolver;

final readonly class Application
{
    public function __construct(
        private JsonRenderer $renderer = new JsonRenderer(),
        private TerminalRenderer $terminalRenderer = new TerminalRenderer(),
        private ?CliParser $parser = null,
        private ?CommandRouter $router = null,
        private ?OutputPreferencesResolver $outputPreferencesResolver = null,
        private ?ExitCodeResolver $exitCodeResolver = null,
        private ConfigLoader $configLoader = new ConfigLoader(),
        private WorkspaceResolver $workspaceResolver = new WorkspaceResolver(),
        private ?Closure $stdoutWriter = null,
        private ?Closure $stderrWriter = null,
        private ?string $cwd = null,
    ) {}

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        try {
            $route = $this->router()->route($this->parser()->parse(array_slice($argv, 1)));
            $preferences = $this->outputPreferencesResolver()->resolve($route);
            $this->writeDebug($route, $preferences);
        } catch (InvalidUsageException $invalidUsageException) {
            return $this->renderUsageError($invalidUsageException, $this->usageErrorPreferences($argv));
        }

        try {
            $preferences = $this->routePreferences($route, $preferences);

            return match ($route->handler()) {
                'help' => $this->renderPassed((new HelpCommand())->handle($route, $this->cwd()), $preferences),
                'version' => $this->renderPassed((new VersionCommand())->handle($route, $this->cwd()), $preferences),
                'init' => $this->renderPassed((new InitCommand())->handle($route, $this->cwd()), $preferences),
                'validate' => $this->renderPassed((new ValidateCommand())->handle($route, $this->cwd()), $preferences),
                'tools.list' => $this->renderToolsList(new ToolsListCommand(), $route, $preferences),
                'skills.help' => $this->renderPassed((new SkillsHelpCommand())->handle($route, $this->cwd()), $preferences),
                'skills.add' => $this->renderPassed((new SkillsAddCommand())->handle($route, $this->cwd()), $preferences),
                'skills.find' => $this->renderPassed((new SkillsFindCommand())->handle($route, $this->cwd()), $preferences),
                'skills.init' => $this->renderPassed((new SkillsInitCommand())->handle($route, $this->cwd()), $preferences),
                'skills.list' => $this->renderPassed((new SkillsListCommand())->handle($route, $this->cwd()), $preferences),
                'skills.remove' => $this->renderPassed((new SkillsRemoveCommand())->handle($route, $this->cwd()), $preferences),
                'skills.update' => $this->renderPassed((new SkillsUpdateCommand())->handle($route, $this->cwd()), $preferences),
                'history.list' => $this->renderPassed((new HistoryListCommand())->handle($route, $this->cwd()), $preferences),
                'history.view' => $this->renderPassed((new HistoryViewCommand())->handle($route, $this->cwd()), $preferences),
                'history.remove' => $this->renderPassed((new HistoryRemoveCommand())->handle($route, $this->cwd()), $preferences),
                'history.clear' => $this->renderPassed((new HistoryClearCommand())->handle($route, $this->cwd()), $preferences),
                'run.tool' => $this->renderRunTool((new RunToolCommand(
                    outputPreferencesResolver: $this->outputPreferencesResolver(),
                    stderrWriter: $this->stderrWriter,
                ))->handle($route, $this->cwd())),
                default => $this->renderNotImplemented($route, $preferences),
            };
        } catch (ConfigValidationException $configValidationException) {
            return $this->renderConfigError($configValidationException, $preferences);
        } catch (InvalidUsageException $invalidUsageException) {
            return $this->renderUsageError($invalidUsageException, $preferences);
        } catch (UserFacingException $userFacingException) {
            return $this->renderUserFacingError($userFacingException, $preferences);
        }
    }

    private function renderRunTool(RunToolCommandResult $result): int
    {
        $payload = $result->payload();

        if ($payload === null) {
            return $result->exitCode();
        }

        return $this->renderPassed($payload, $result->outputPreferences());
    }

    private function renderToolsList(ToolsListCommand $command, CommandRoute $route, OutputPreferences $preferences): int
    {
        $payload = $command->streamTerminal(
            route: $route,
            cwd: $this->cwd(),
            writer: $this->writeStdout(...),
            renderer: $this->terminalRenderer,
            preferences: $preferences,
        );

        $status = $payload['status'] ?? RunStatus::Passed->value;
        $runStatus = is_string($status) ? RunStatus::tryFrom($status) : null;

        return $this->exitCodeResolver()->resolve($runStatus ?? RunStatus::Passed)->value;
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

    /**
     * @param list<string> $argv
     */
    private function usageErrorPreferences(array $argv): OutputPreferences
    {
        $defaults = $this->outputPreferencesResolver()->defaults();
        $color = ! in_array('--no-color', array_slice($argv, 1), true)
            && $defaults->color();

        if (! in_array('--json', array_slice($argv, 1), true)) {
            return new OutputPreferences(
                size: $defaults->size(),
                pretty: $defaults->pretty(),
                showProcess: $defaults->showProcess(),
                debug: $defaults->debug(),
                format: $defaults->format(),
                color: $color,
            );
        }

        return new OutputPreferences(
            size: $defaults->size(),
            pretty: $defaults->pretty(),
            showProcess: $defaults->showProcess(),
            debug: $defaults->debug(),
            format: OutputFormat::Json,
            color: $color,
        );
    }

    private function cwd(): string
    {
        if ($this->cwd !== null) {
            return $this->cwd;
        }

        $cwd = getcwd();

        return is_string($cwd) ? $cwd : '.';
    }

    private function routePreferences(CommandRoute $route, OutputPreferences $fallback): OutputPreferences
    {
        if (in_array($route->handler(), ['help', 'version', 'tools.list', 'skills.help'], true)) {
            return $this->terminalPreferences($fallback);
        }

        if ($route->handler() === 'init') {
            return $fallback;
        }

        $workspace = $this->workspaceResolver->resolve($this->cwd(), $this->configPath($route));
        $config = $this->configLoader->load($workspace);
        $resolved = $this->outputPreferencesResolver()->resolve($route, $config);

        if (str_starts_with($route->handler(), 'skills.') && ! $this->jsonRequested($route)) {
            return $this->terminalPreferences($resolved);
        }

        return $resolved;
    }

    private function terminalPreferences(OutputPreferences $preferences): OutputPreferences
    {
        return new OutputPreferences(
            size: $preferences->size(),
            pretty: $preferences->pretty(),
            showProcess: $preferences->showProcess(),
            debug: $preferences->debug(),
            format: OutputFormat::Terminal,
            color: $preferences->color(),
        );
    }

    private function configPath(CommandRoute $route): ?string
    {
        $options = $route->options();
        $globalOptions = $route->globalOptions();
        $config = $options['config'] ?? $globalOptions['config'] ?? null;

        return is_string($config) ? $config : null;
    }

    private function jsonRequested(CommandRoute $route): bool
    {
        return ($route->globalOptions()['json'] ?? false) === true || ($route->options()['json'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPassed(array $payload, OutputPreferences $preferences): int
    {
        $this->writeStdout($this->renderPayload($payload, $preferences));
        $status = $payload['status'] ?? RunStatus::Passed->value;
        $runStatus = is_string($status) ? RunStatus::tryFrom($status) : null;

        return $this->exitCodeResolver()->resolve($runStatus ?? RunStatus::Passed)->value;
    }

    private function renderUsageError(InvalidUsageException $exception, OutputPreferences $preferences): int
    {
        $this->writeStderr($this->renderPayload(ErrorPayload::fromInvalidUsage($exception), $preferences));

        return $this->exitCodeResolver()->resolve(RunStatus::Error, ErrorCode::InvalidUsage)->value;
    }

    private function renderConfigError(ConfigValidationException $exception, OutputPreferences $preferences): int
    {
        $this->writeStderr($this->renderPayload(ErrorPayload::fromConfigValidation($exception), $preferences));
        $errorCode = ErrorCode::tryFrom($exception->errorCode()) ?? ErrorCode::InvalidConfig;

        return $this->exitCodeResolver()->resolve(RunStatus::Error, $errorCode)->value;
    }

    private function renderUserFacingError(UserFacingException $exception, OutputPreferences $preferences): int
    {
        $this->writeStderr($this->renderPayload(ErrorPayload::fromUserFacing($exception), $preferences));

        return $this->exitCodeResolver()->resolve(RunStatus::Error, $exception->errorCode())->value;
    }

    private function renderNotImplemented(CommandRoute $route, OutputPreferences $preferences): int
    {
        $this->writeStderr($this->renderPayload(ErrorPayload::make(
            errorCode: ErrorCode::InvalidUsage,
            message: sprintf('Command "%s" is not implemented yet.', $route->handler()),
            hint: 'Run "sift help" to list available commands.',
        ), $preferences));

        return $this->exitCodeResolver()->resolve(RunStatus::Error, ErrorCode::InvalidUsage)->value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPayload(array $payload, OutputPreferences $preferences): string
    {
        if ($preferences->format() === OutputFormat::Json) {
            return $this->renderer->render($payload, $preferences);
        }

        return $this->terminalRenderer->render($payload, $preferences);
    }

    private function writeStdout(string $contents): void
    {
        if ($this->stdoutWriter instanceof Closure) {
            ($this->stdoutWriter)($contents);

            return;
        }

        fwrite(STDOUT, $contents);
        fflush(STDOUT);
    }

    private function writeStderr(string $contents): void
    {
        if ($this->stderrWriter instanceof Closure) {
            ($this->stderrWriter)($contents);

            return;
        }

        fwrite(STDERR, $contents);
    }

    private function writeDebug(CommandRoute $route, OutputPreferences $preferences): void
    {
        if (! $preferences->debug()) {
            return;
        }

        $payload = [
            'tool' => 'sift',
            'type' => 'debug',
            'handler' => $route->handler(),
            'cwd' => $this->cwd(),
            'arguments' => $route->arguments(),
            'options' => $route->options(),
            'global_options' => $route->globalOptions(),
            'output' => [
                'format' => $preferences->format()->value,
                'size' => $preferences->size()->value,
                'pretty' => $preferences->pretty(),
                'show_process' => $preferences->showProcess(),
                'color' => $preferences->color(),
            ],
        ];

        $this->writeStderr(json_encode((new SecretRedactor())->redactPayload($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}
