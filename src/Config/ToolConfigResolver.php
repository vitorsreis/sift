<?php

declare(strict_types=1);

namespace Sift\Config;

final class ToolConfigResolver
{
    public function resolve(SiftConfig $config, string $tool): ToolConfig
    {
        $wildcard = $config->tool('*') ?? new ToolConfig('*', true, null, [], 1800);
        $configured = $config->tool($tool);

        if ($configured instanceof ToolConfig) {
            return $configured;
        }

        return new ToolConfig(
            name: $tool,
            enabled: $wildcard->enabled(),
            binary: null,
            blockedArgs: [],
            timeout: $wildcard->timeout(),
        );
    }
}
