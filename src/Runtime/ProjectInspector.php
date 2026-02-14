<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Registry\ToolRegistry;

final readonly class ProjectInspector
{
    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function inspect(string $cwd, ToolRegistry $registry, array $config = []): array
    {
        $items = [];

        foreach ($registry->all() as $tool) {
            $toolConfig = is_array($config['tools'][$tool->name()] ?? null)
                ? $config['tools'][$tool->name()]
                : [];
            $configuredBinary = is_string($toolConfig['toolBinary'] ?? null) && trim((string) $toolConfig['toolBinary']) !== ''
                ? trim((string) $toolConfig['toolBinary'])
                : null;
            $candidates = $configuredBinary !== null
                ? [$configuredBinary]
                : $tool->discoveryCandidates();
            $resolved = $this->toolLocator->locate($cwd, $candidates);

            $items[] = [
                'tool' => $tool->name(),
                'enabled' => (bool) ($toolConfig['enabled'] ?? true),
                'installed' => $resolved !== null,
                'path' => $resolved['path'] ?? null,
                'configured_binary' => $configuredBinary,
            ];
        }

        return $items;
    }
}
