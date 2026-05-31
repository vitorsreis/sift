<?php

declare(strict_types=1);

namespace Sift\Config;

final readonly class SiftConfig
{
    /**
     * @param array<string, ToolConfig> $tools
     */
    public function __construct(
        private string $schema,
        private ?string $configPath,
        private bool $usingDefaults,
        private HistoryConfig $history,
        private OutputConfig $output,
        private array $tools,
    ) {}

    public function schema(): string
    {
        return $this->schema;
    }

    public function configPath(): ?string
    {
        return $this->configPath;
    }

    public function usingDefaults(): bool
    {
        return $this->usingDefaults;
    }

    public function history(): HistoryConfig
    {
        return $this->history;
    }

    public function output(): OutputConfig
    {
        return $this->output;
    }

    /**
     * @return array<string, ToolConfig>
     */
    public function tools(): array
    {
        return array_filter(
            $this->tools,
            static fn(string $name): bool => $name !== '*',
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function tool(string $name): ?ToolConfig
    {
        return $this->tools[$name] ?? null;
    }
}
