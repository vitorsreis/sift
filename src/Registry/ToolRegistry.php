<?php

declare(strict_types=1);

namespace Sift\Registry;

use Sift\Contracts\ToolAdapterInterface;

final readonly class ToolRegistry
{
    /**
     * @param  list<ToolAdapterInterface>  $tools
     */
    public function __construct(
        private array $tools = [],
    ) {}

    /**
     * @return list<ToolAdapterInterface>
     */
    public function all(): array
    {
        return $this->tools;
    }

    public function find(string $name): ?ToolAdapterInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(
            static fn (ToolAdapterInterface $tool): string => $tool->name(),
            $this->tools,
        );
    }
}
