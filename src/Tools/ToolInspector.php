<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Config\ToolConfigResolver;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\LocatedTool;
use Sift\Execution\ProcessRunner;
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
    ) {}

    /**
     * @return list<array{tool: string, enabled: bool, installed: bool, status: string, version: ?string, path: ?string, configured_binary: ?string, install_hint: string}>
     */
    public function inspect(SiftConfig $config, string $cwd): array
    {
        $items = [];

        foreach ($this->registry->all() as $adapter) {
            $definition = $adapter->definition();
            $toolConfig = $this->configResolver->resolve($config, $definition->name());
            $locatedTool = $this->locate($definition, $toolConfig, $cwd);
            $installed = $locatedTool instanceof LocatedTool;
            $enabled = $toolConfig->enabled();

            $items[] = [
                'tool' => $definition->name(),
                'enabled' => $enabled,
                'installed' => $installed,
                'status' => $enabled && $installed ? 'ON' : 'OFF',
                'version' => $locatedTool instanceof LocatedTool ? $this->version($definition, $locatedTool, $toolConfig, $cwd) : null,
                'path' => $locatedTool?->binary(),
                'configured_binary' => $toolConfig->binary(),
                'install_hint' => $definition->installHint(),
            ];
        }

        return $items;
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

        $cacheKey = implode("\0", [$cwd, $tool->binary(), ...$definition->versionCommand()]);

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

        $version = trim($execution->stdout()) !== ''
            ? trim($execution->stdout())
            : trim($execution->stderr());

        $this->versionCache[$cacheKey] = $version === '' ? null : $version;

        return $this->versionCache[$cacheKey];
    }
}
