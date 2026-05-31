<?php

declare(strict_types=1);

namespace Sift\Registry;

use Sift\Tools\ToolAdapter;

interface ToolRegistryInterface
{
    public function find(string $name): ?ToolAdapter;

    /**
     * @return list<ToolAdapter>
     */
    public function all(): array;
}
