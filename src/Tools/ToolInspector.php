<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Config\ToolConfigResolver;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\LocatedTool;
use Sift\Execution\ProcessCommandBuilder;
use Sift\Execution\ProcessRunner;
use Sift\Execution\ProcessTreeTerminator;
use Sift\Execution\ToolResolver;
use Sift\Registry\ToolRegistryInterface;

final class ToolInspector
{
    /**
     * @var array<string, ?string>
     */
    private array $versionCache = [];

    public function __construct(
        private readonly ToolRegistryInterface $registry,
        private readonly ToolConfigResolver $configResolver = new ToolConfigResolver(),
        private readonly ToolResolver $toolResolver = new ToolResolver(),
        private readonly ProcessRunner $processRunner = new ProcessRunner(),
        private readonly ProcessCommandBuilder $commandBuilder = new ProcessCommandBuilder(),
        private readonly ProcessTreeTerminator $processTerminator = new ProcessTreeTerminator(),
    ) {}

    /**
     * @return list<array{tool: string, enabled: bool, installed: bool, status: string, version: ?string, path: ?string, configured_binary: ?string, install_hint: string}>
     */
    public function inspect(SiftConfig $config, string $cwd): array
    {
        $items = [];

        foreach ($this->inspectEach($config, $cwd) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return iterable<array{tool: string, description: string, enabled: bool, installed: bool, status: string, version: ?string, path: ?string, configured_binary: ?string, install_hint: string}>
     */
    public function inspectEach(SiftConfig $config, string $cwd, int $versionConcurrency = 1): iterable
    {
        if ($versionConcurrency > 1) {
            yield from $this->inspectEachParallel($config, $cwd, $versionConcurrency);

            return;
        }

        foreach ($this->registry->all() as $adapter) {
            $definition = $adapter->definition();
            $toolConfig = $this->configResolver->resolve($config, $definition->name());
            $locatedTool = $this->locate($definition, $toolConfig, $cwd);
            $installed = $locatedTool instanceof LocatedTool;
            $enabled = $toolConfig->enabled();

            $item = $this->item($definition, $toolConfig, $locatedTool, $enabled, $installed);

            yield [
                ...$item,
                'version' => $locatedTool instanceof LocatedTool ? $this->version($definition, $locatedTool, $toolConfig, $cwd) : null,
            ];
        }
    }

    /**
     * @return iterable<array{tool: string, description: string, enabled: bool, installed: bool, status: string, version: ?string, path: ?string, configured_binary: ?string, install_hint: string}>
     */
    private function inspectEachParallel(SiftConfig $config, string $cwd, int $versionConcurrency): iterable
    {
        $jobs = [];

        foreach ($this->registry->all() as $adapter) {
            $definition = $adapter->definition();
            $toolConfig = $this->configResolver->resolve($config, $definition->name());
            $locatedTool = $this->locate($definition, $toolConfig, $cwd);
            $installed = $locatedTool instanceof LocatedTool;
            $enabled = $toolConfig->enabled();
            $item = $this->item($definition, $toolConfig, $locatedTool, $enabled, $installed);

            if (! $locatedTool instanceof LocatedTool || $definition->versionCommand() === []) {
                yield [
                    ...$item,
                    'version' => null,
                ];

                continue;
            }

            $cacheKey = $this->versionCacheKey($definition, $locatedTool, $cwd);

            if (array_key_exists($cacheKey, $this->versionCache)) {
                yield [
                    ...$item,
                    'version' => $this->versionCache[$cacheKey],
                ];

                continue;
            }

            $jobs[] = [
                'definition' => $definition,
                'config' => $toolConfig,
                'tool' => $locatedTool,
                'item' => $item,
                'cache_key' => $cacheKey,
            ];
        }

        foreach (array_chunk($jobs, max(1, $versionConcurrency)) as $chunk) {
            yield from $this->runVersionChunk($chunk, $cwd);
        }
    }

    /**
     * @return array{tool: string, description: string, enabled: bool, installed: bool, status: string, path: ?string, configured_binary: ?string, install_hint: string}
     */
    private function item(ToolDefinition $definition, ToolConfig $toolConfig, ?LocatedTool $locatedTool, bool $enabled, bool $installed): array
    {
        return [
            'tool' => $definition->name(),
            'description' => $definition->description(),
            'enabled' => $enabled,
            'installed' => $installed,
            'status' => $enabled && $installed ? 'ON' : 'OFF',
            'path' => $locatedTool?->binary(),
            'configured_binary' => $toolConfig->binary(),
            'install_hint' => $definition->installHint(),
        ];
    }

    private function locate(ToolDefinition $definition, ToolConfig $config, string $cwd): ?LocatedTool
    {
        try {
            return $this->toolResolver->resolve(
                definition: $definition,
                config: new ToolConfig(
                    name: $config->name(),
                    enabled: true,
                    binary: $config->binary(),
                    blockedArgs: $config->blockedArgs(),
                    timeout: $config->timeout(),
                ),
                cwd: $cwd,
            );
        } catch (UserFacingException $userFacingException) {
            if ($userFacingException->errorCode() === ErrorCode::ToolNotFound) {
                return null;
            }

            throw $userFacingException;
        }
    }

    private function version(ToolDefinition $definition, LocatedTool $tool, ToolConfig $config, string $cwd): ?string
    {
        if ($definition->versionCommand() === []) {
            return null;
        }

        $cacheKey = $this->versionCacheKey($definition, $tool, $cwd);

        if (array_key_exists($cacheKey, $this->versionCache)) {
            return $this->versionCache[$cacheKey];
        }

        $execution = $this->processRunner->run(new PreparedCommand(
            tool: $definition->name(),
            binary: $tool->binary(),
            arguments: $definition->versionCommand(),
            cwd: $cwd,
            timeout: $config->timeout(),
        ));

        $this->versionCache[$cacheKey] = $this->versionFromExecution($execution);

        return $this->versionCache[$cacheKey];
    }

    private function versionCacheKey(ToolDefinition $definition, LocatedTool $tool, string $cwd): string
    {
        return implode("\0", [$cwd, $tool->binary(), ...$definition->versionCommand()]);
    }

    private function versionFromExecution(ExecutionResult $execution): ?string
    {
        $version = trim($execution->stdout()) !== ''
            ? trim($execution->stdout())
            : trim($execution->stderr());

        if (! $execution->successful() && ! $this->containsVersion($version)) {
            return null;
        }

        $version = preg_replace('/\e\[[\d;]*m/', '', $version);
        $version = is_string($version) ? trim($version) : '';

        return $version === '' ? null : $version;
    }

    private function containsVersion(string $value): bool
    {
        return preg_match('/\bv?\d+(?:\.\d+)+(?:[-+@][^\s]+)?\b/i', $value) === 1;
    }

    /**
     * @param list<array{definition: ToolDefinition, config: ToolConfig, tool: LocatedTool, item: array{tool: string, description: string, enabled: bool, installed: bool, status: string, path: ?string, configured_binary: ?string, install_hint: string}, cache_key: string}> $jobs
     *
     * @return iterable<array{tool: string, description: string, enabled: bool, installed: bool, status: string, version: ?string, path: ?string, configured_binary: ?string, install_hint: string}>
     */
    private function runVersionChunk(array $jobs, string $cwd): iterable
    {
        $running = [];

        foreach ($jobs as $job) {
            $running[] = $this->startVersionJob($job, $cwd);
        }

        while ($running !== []) {
            foreach ($running as $index => $job) {
                if (! is_resource($job['process'])) {
                    unset($running[$index]);

                    $this->versionCache[$job['cache_key']] = null;

                    yield $this->itemWithVersion($job['item'], null);

                    continue;
                }

                if ($this->versionJobTimedOut($job)) {
                    $this->processTerminator->terminate($job['process']);
                    $this->closeVersionJob($job);
                    unset($running[$index]);

                    $this->versionCache[$job['cache_key']] = null;

                    yield $this->itemWithVersion($job['item'], null);

                    continue;
                }

                $status = proc_get_status($job['process']);

                if ($status['running']) {
                    continue;
                }

                $exitCode = $status['exitcode'] >= 0 ? $status['exitcode'] : proc_close($job['process']);
                if ($status['exitcode'] >= 0) {
                    proc_close($job['process']);
                }

                $duration = microtime(true) - $job['started_at'];
                $stdout = $this->readVersionOutput($job['stdout_path']);
                $stderr = $this->readVersionOutput($job['stderr_path']);
                $this->cleanupVersionJob($job);
                unset($running[$index]);

                $version = $this->versionFromExecution(ExecutionResult::completed($exitCode, $stdout, $stderr, $duration));
                $this->versionCache[$job['cache_key']] = $version;

                yield $this->itemWithVersion($job['item'], $version);
            }

            if ($running !== []) {
                usleep(10_000);
            }
        }
    }

    /**
     * @param array{definition: ToolDefinition, config: ToolConfig, tool: LocatedTool, item: array{tool: string, description: string, enabled: bool, installed: bool, status: string, path: ?string, configured_binary: ?string, install_hint: string}, cache_key: string} $job
     *
     * @return array{process: resource|null, stdout_path: string, stderr_path: string, started_at: float, timeout: int, item: array{tool: string, description: string, enabled: bool, installed: bool, status: string, path: ?string, configured_binary: ?string, install_hint: string}, cache_key: string}
     */
    private function startVersionJob(array $job, string $cwd): array
    {
        $stdoutPath = $this->temporaryPath('sift-version-out-');
        $stderrPath = $this->temporaryPath('sift-version-err-');
        $pipes = [];
        $command = new PreparedCommand(
            tool: $job['definition']->name(),
            binary: $job['tool']->binary(),
            arguments: $job['definition']->versionCommand(),
            cwd: $cwd,
            timeout: $job['config']->timeout(),
        );
        $process = @proc_open(
            $this->commandBuilder->argv($command),
            [
                0 => ['pipe', 'r'],
                1 => ['file', $stdoutPath, 'w'],
                2 => ['file', $stderrPath, 'w'],
            ],
            $pipes,
            $cwd,
        );

        if (! is_resource($process)) {
            @unlink($stdoutPath);
            @unlink($stderrPath);

            return [
                'process' => null,
                'stdout_path' => '',
                'stderr_path' => '',
                'started_at' => microtime(true),
                'timeout' => 0,
                'item' => $job['item'],
                'cache_key' => $job['cache_key'],
            ];
        }

        if (is_resource($pipes[0] ?? null)) {
            fclose($pipes[0]);
        }

        return [
            'process' => $process,
            'stdout_path' => $stdoutPath,
            'stderr_path' => $stderrPath,
            'started_at' => microtime(true),
            'timeout' => $job['config']->timeout(),
            'item' => $job['item'],
            'cache_key' => $job['cache_key'],
        ];
    }

    /**
     * @param array{started_at: float, timeout: int} $job
     */
    private function versionJobTimedOut(array $job): bool
    {
        return $job['timeout'] > 0 && microtime(true) - $job['started_at'] >= $job['timeout'];
    }

    /**
     * @param array{tool: string, description: string, enabled: bool, installed: bool, status: string, path: ?string, configured_binary: ?string, install_hint: string} $item
     *
     * @return array{tool: string, description: string, enabled: bool, installed: bool, status: string, version: ?string, path: ?string, configured_binary: ?string, install_hint: string}
     */
    private function itemWithVersion(array $item, ?string $version): array
    {
        return [
            ...$item,
            'version' => $version,
        ];
    }

    /**
     * @param array{process: resource|null, stdout_path: string, stderr_path: string} $job
     */
    private function closeVersionJob(array $job): void
    {
        if (is_resource($job['process'])) {
            proc_close($job['process']);
        }

        $this->cleanupVersionJob($job);
    }

    /**
     * @param array{stdout_path: string, stderr_path: string} $job
     */
    private function cleanupVersionJob(array $job): void
    {
        if ($job['stdout_path'] !== '') {
            @unlink($job['stdout_path']);
        }

        if ($job['stderr_path'] !== '') {
            @unlink($job['stderr_path']);
        }
    }

    private function readVersionOutput(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: 'Could not create version output spool file.',
            );
        }

        return $path;
    }
}
