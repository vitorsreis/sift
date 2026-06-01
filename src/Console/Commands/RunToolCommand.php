<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Config\ConfigLoader;
use Sift\Config\HistoryConfig;
use Sift\Console\CommandRoute;
use Sift\Console\OutputPreferencesResolver;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\FilesystemException;
use Sift\History\RunHistoryService;
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
        $result = $this->toolRunner->run(
            arguments: CliArguments::fromRoute($route),
            config: $config,
            cwd: $workspace->projectRoot(),
        );

        if ($result instanceof ExecutionResult) {
            return RunToolCommandResult::raw($result->exitCode(), $preferences);
        }

        $payload = $result->toPayload();
        $this->recordHistory($payload, $this->historyConfig($route, $config->history()));

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

    /**
     * @param array<string, mixed> $payload
     */
    private function recordHistory(array $payload, HistoryConfig $config): void
    {
        try {
            $this->historyService->record($payload, $config);
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
}
