<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Config\ConfigLoader;
use Sift\Console\CommandRoute;
use Sift\Workspace\WorkspaceResolver;

final readonly class ValidateCommand implements CommandHandler
{
    public function __construct(
        private ConfigLoader $configLoader = new ConfigLoader(),
        private WorkspaceResolver $workspaceResolver = new WorkspaceResolver(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CommandRoute $route, string $cwd): array
    {
        $workspace = $this->workspaceResolver->resolve($cwd, $this->configPath($route));
        $config = $this->configLoader->load($workspace);

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'config_path' => $config->configPath(),
                'schema' => $config->schema(),
                'using_defaults' => $config->usingDefaults(),
            ],
            'items' => [],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'validate',
                'warnings' => [],
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
