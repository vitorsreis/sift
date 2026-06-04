<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Closure;
use Sift\Config\ConfigLoader;
use Sift\Config\HistoryConfig;
use Sift\Config\SiftConfig;
use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\History\FileRunStore;
use Sift\History\RunIdFormat;
use Sift\History\RunStore;
use Sift\Workspace\WorkspaceResolver;

abstract readonly class AbstractHistoryCommand implements CommandHandler
{
    /**
     * @var Closure(HistoryConfig): RunStore
     */
    private Closure $storeFactory;

    /**
     * @param (callable(HistoryConfig): RunStore)|null $storeFactory
     */
    public function __construct(
        ?callable $storeFactory = null,
        private ConfigLoader $configLoader = new ConfigLoader(),
        private WorkspaceResolver $workspaceResolver = new WorkspaceResolver(),
    ) {
        $this->storeFactory = Closure::fromCallable(
            $storeFactory ?? static fn(HistoryConfig $config): RunStore => new FileRunStore($config->path(), removeDefaultParentOnClear: $config->defaultPath()),
        );
    }

    protected function config(CommandRoute $route, string $cwd): SiftConfig
    {
        return $this->configLoader->load($this->workspaceResolver->resolve($cwd, $this->configPath($route)));
    }

    protected function store(CommandRoute $route, string $cwd): RunStore
    {
        return ($this->storeFactory)($this->config($route, $cwd)->history());
    }

    protected function intOption(CommandRoute $route, string $name, int $default): int
    {
        $value = $route->options()[$name] ?? null;

        return is_int($value) ? $value : $default;
    }

    protected function runId(string $value): string
    {
        if (! RunIdFormat::isValid($value)) {
            throw new InvalidUsageException(sprintf('Invalid run id "%s".', $value));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function objectValue(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            return [];
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }

    /**
     * @return list<mixed>
     */
    protected function listValue(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    private function configPath(CommandRoute $route): ?string
    {
        $options = $route->options();
        $globalOptions = $route->globalOptions();
        $config = $options['config'] ?? $globalOptions['config'] ?? null;

        return is_string($config) ? $config : null;
    }
}
