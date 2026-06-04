<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Closure;
use Sift\Config\ConfigLoader;
use Sift\Config\HistoryConfig;
use Sift\Console\CommandRoute;
use Sift\Console\OutputPreferences;
use Sift\Console\OutputPreferencesResolver;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\ProcessTailRenderer;
use Sift\Filesystem\FilesystemException;
use Sift\History\RunHistoryService;
use Sift\Output\ErrorPayload;
use Sift\Registry\ToolRegistry;
use Sift\Safety\BlockedArgumentsPolicy;
use Sift\Safety\ComposerReadOnlyPolicy;
use Sift\Safety\MachineOutputPolicy;
use Sift\Safety\MagoSafeModePolicy;
use Sift\Safety\PolicyPipeline;
use Sift\Safety\RectorDryRunPolicy;
use Sift\Tools\CliArguments;
use Sift\Tools\ToolRunner;
use Sift\Workspace\WorkspaceResolver;

final readonly class RunToolCommand
{
    private ToolRunner $toolRunner;

    public function __construct(
        ?ToolRunner $toolRunner = null,
        private ConfigLoader $configLoader = new ConfigLoader(),
        private WorkspaceResolver $workspaceResolver = new WorkspaceResolver(),
        private OutputPreferencesResolver $outputPreferencesResolver = new OutputPreferencesResolver(),
        private RunHistoryService $historyService = new RunHistoryService(),
        private ?Closure $stderrWriter = null,
        private ProcessTailRenderer $processTailRenderer = new ProcessTailRenderer(),
    ) {
        $this->toolRunner = $toolRunner ?? new ToolRunner(
            registry: ToolRegistry::builtIns(),
            policyPipeline: new PolicyPipeline([
                new BlockedArgumentsPolicy(),
                new ComposerReadOnlyPolicy(),
                new RectorDryRunPolicy(),
                new MagoSafeModePolicy(),
                new MachineOutputPolicy(),
            ]),
        );
    }

    public function handle(CommandRoute $route, string $cwd): RunToolCommandResult
    {
        $workspace = $this->workspaceResolver->resolve($cwd, $this->configPath($route));
        $config = $this->configLoader->load($workspace);
        $preferences = $this->outputPreferencesResolver->resolve($route, $config);
        $arguments = CliArguments::fromRoute($route);
        $historyConfig = $this->historyConfig($route, $config->history());

        try {
            $result = $this->toolRunner->run(
                arguments: $arguments,
                config: $config,
                cwd: $workspace->projectRoot(),
                processReporter: $this->processReporter($preferences),
            );
        } catch (UserFacingException $userFacingException) {
            throw $this->withHistoryRunId($userFacingException, $historyConfig, $arguments->tool());
        }

        if ($result instanceof ExecutionResult) {
            return RunToolCommandResult::raw($result->exitCode(), $preferences);
        }

        $payload = $result->toPayload();
        $history = $this->recordHistory($payload, $historyConfig);

        $payload = $this->withPayloadRunId($payload, $this->runId($history));

        return RunToolCommandResult::normalized($payload, $preferences);
    }

    private function configPath(CommandRoute $route): ?string
    {
        $options = $route->options();
        $globalOptions = $route->globalOptions();
        $config = $options['config'] ?? $globalOptions['config'] ?? null;

        return is_string($config) ? $config : null;
    }

    private function historyConfig(CommandRoute $route, HistoryConfig $config): HistoryConfig
    {
        $enabled = match (true) {
            $this->optionBool($route, 'no-history') => false,
            $this->optionBool($route, 'history') => true,
            default => $config->enabled(),
        };

        return new HistoryConfig(
            enabled: $enabled,
            path: $config->path(),
            maxFiles: $config->maxFiles(),
            maxAgeDays: $config->maxAgeDays(),
            maxBytesPerRun: $config->maxBytesPerRun(),
            redactSecrets: $config->redactSecrets(),
            defaultPath: $config->defaultPath(),
        );
    }

    private function optionBool(CommandRoute $route, string $name): bool
    {
        return ($route->globalOptions()[$name] ?? $route->options()[$name] ?? false) === true;
    }

    private function processReporter(OutputPreferences $preferences): ?Closure
    {
        if (! $preferences->showProcess()) {
            return null;
        }

        return function (PreparedCommand $command): void {
            $this->writeStderr($this->processTailRenderer->render($command));
        };
    }

    private function writeStderr(string $contents): void
    {
        if ($this->stderrWriter instanceof Closure) {
            ($this->stderrWriter)($contents);

            return;
        }

        fwrite(STDERR, $contents);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function recordHistory(array $payload, HistoryConfig $config): ?array
    {
        try {
            return $this->historyService->record($payload, $config);
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::HistoryWriteFailed,
                message: 'Could not write history run.',
                hint: 'Check history.path permissions or run again with --no-history.',
                context: [
                    'path' => $config->path(),
                    'detail' => $filesystemException->getMessage(),
                ],
            );
        }
    }

    private function withHistoryRunId(UserFacingException $exception, HistoryConfig $config, string $tool): UserFacingException
    {
        $payload = [
            'tool' => $tool,
            ...ErrorPayload::fromUserFacing($exception),
        ];
        $history = $this->recordHistory($payload, $config);
        $runId = $this->runId($history);

        if ($runId === null) {
            return $exception;
        }

        return UserFacingException::withContext(
            errorCode: $exception->errorCode(),
            message: $exception->getMessage(),
            hint: $exception->hint(),
            context: [
                'tool' => $tool,
                ...$exception->context(),
                'run_id' => $runId,
            ],
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function withPayloadRunId(array $payload, ?string $runId): array
    {
        if ($runId === null) {
            return $payload;
        }

        if (($payload['status'] ?? null) === RunStatus::Error->value) {
            $error = $payload['error'] ?? [];

            if (! is_array($error) || array_is_list($error)) {
                $error = [];
            }

            return [
                ...$payload,
                'error' => [
                    ...$error,
                ],
            ];
        }

        return [
            'run_id' => $runId,
            ...$payload,
        ];
    }

    /**
     * @param array<string, mixed>|null $history
     */
    private function runId(?array $history): ?string
    {
        $runId = $history['run_id'] ?? null;

        return is_string($runId) && $runId !== '' ? $runId : null;
    }
}
