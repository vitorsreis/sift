<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Config\ConfigDefaults;
use Sift\Config\ConfigLoader;
use Sift\Config\ConfigSchemaValidator;
use Sift\Console\CommandRoute;
use Sift\Console\ResourcePathResolver;
use Sift\Filesystem\JsonFile;
use Sift\Workspace\WorkspaceResolver;

final readonly class ValidateCommand implements CommandHandler
{
    public function __construct(
        private ConfigLoader $configLoader = new ConfigLoader(),
        private WorkspaceResolver $workspaceResolver = new WorkspaceResolver(),
        private ConfigSchemaValidator $schemaValidator = new ConfigSchemaValidator(),
        private JsonFile $jsonFile = new JsonFile(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CommandRoute $route, string $cwd): array
    {
        $workspace = $this->workspaceResolver->resolve($cwd, $this->configPath($route));
        $configPath = $workspace->configPath();
        $document = $configPath !== null && is_file($configPath)
            ? $this->configLoader->readDocument($configPath)
            : ConfigDefaults::document();
        $schema = $this->jsonFile->readObject(ResourcePathResolver::fromRuntime()->resource('schema.json'));
        $this->schemaValidator->validate($document, $schema, $configPath);
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
