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
    public function inspect(string $cwd, ToolRegistry $registry): array
    {
        $items = [];

        foreach ($registry->all() as $tool) {
            $resolved = $this->toolLocator->locate($cwd, $tool->discoveryCandidates());

            $items[] = [
                'tool' => $tool->name(),
                'installed' => $resolved !== null,
                'path' => $resolved['path'] ?? null,
            ];
        }

        return $items;
    }
}
