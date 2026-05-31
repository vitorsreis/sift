<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Config\ConfigLoader;
use Sift\Console\CommandRoute;
use Sift\Registry\ToolRegistry;
use Sift\Tools\ToolInspector;
use Sift\Workspace\WorkspaceResolver;

final readonly class ToolsListCommand implements CommandHandler
{
    private ToolInspector $toolInspector;

    public function __construct(
        ?ToolInspector $toolInspector = null,
        private ConfigLoader $configLoader = new ConfigLoader(),
        private WorkspaceResolver $workspaceResolver = new WorkspaceResolver(),
    ) {
        $this->toolInspector = $toolInspector ?? new ToolInspector(ToolRegistry::builtIns());
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(CommandRoute $route, string $cwd): array
    {
        $workspace = $this->workspaceResolver->resolve($cwd, $this->configPath($route));
        $config = $this->configLoader->load($workspace);
        $items = $this->toolInspector->inspect($config, $workspace->projectRoot());

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'supported' => count($items),
                'installed' => count(array_filter($items, static fn(array $item): bool => $item['installed'] === true)),
                'enabled' => count(array_filter($items, static fn(array $item): bool => $item['enabled'] === true)),
            ],
            'items' => $items,
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'tools list',
            ],
        ];
    }

    private function configPath(CommandRoute $route): ?string
    {
        $options = $route->options();
        $globalOptions = $route->globalOptions();
        $config = $options['config'] ?? $globalOptions['config'] ?? null;

        return is_string($config) ? $config : null;
    }
}
