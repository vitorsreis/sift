<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Config\ConfigLoader;
use Sift\Config\ConfigWriter;
use Sift\Console\CommandRoute;
use Sift\Filesystem\Path;
use Sift\Workspace\WorkspaceResolver;

final readonly class InitCommand implements CommandHandler
{
    public function __construct(
        private ConfigLoader $configLoader = new ConfigLoader(),
        private ConfigWriter $configWriter = new ConfigWriter(),
        private WorkspaceResolver $workspaceResolver = new WorkspaceResolver(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CommandRoute $route, string $cwd): array
    {
        $workspace = $this->workspaceResolver->resolve($cwd, $this->configPath($route));
        $configPath = $workspace->configPath() ?? Path::join($workspace->projectRoot(), 'sift.json');
        $force = $this->optionBool($route, 'force');
        $alreadyInitialized = false;
        $existing = null;

        if (is_file($configPath)) {
            $validatedWorkspace = $this->workspaceResolver->resolve($cwd, $configPath);
            $this->configLoader->load($validatedWorkspace);

            if (! $force) {
                $alreadyInitialized = true;
            } else {
                $existing = $this->configLoader->readDocument($configPath);
                $this->configLoader->validateSchema($existing, $configPath);
            }
        }

        if (! $alreadyInitialized) {
            $this->configWriter->writeDefaults($configPath, $existing);
        }

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'config_path' => $configPath,
                'already_initialized' => $alreadyInitialized,
                'skill_installed' => false,
            ],
            'items' => [],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'init',
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

    private function optionBool(CommandRoute $route, string $name): bool
    {
        $value = $route->options()[$name] ?? false;

        return $value === true;
    }
}
