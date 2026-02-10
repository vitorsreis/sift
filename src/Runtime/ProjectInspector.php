<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Registry\ToolRegistry;

final class ProjectInspector
{
    public function __construct(
        private readonly ToolLocator $toolLocator,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function inspect(string $cwd, ToolRegistry $registry, array $config = []): array
    {
        $items = [];

        foreach ($registry->all() as $tool) {
            $resolved = $this->toolLocator->locate($cwd, $tool->discoveryCandidates());
            $toolConfig = is_array($config['tools'][$tool->name()] ?? null)
                ? $config['tools'][$tool->name()]
                : [];

            $items[] = [
                'tool' => $tool->name(),
                'enabled' => (bool) ($toolConfig['enabled'] ?? true),
                'installed' => $resolved !== null,
                'path' => $resolved['path'] ?? null,
            ];
        }

        return $items;
    }
}
