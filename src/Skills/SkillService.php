<?php

declare(strict_types=1);

namespace Sift\Skills;

use Closure;

final class SkillService
{
    /**
     * @var array<string, list<SkillManagedMetadata>>
     */
    private array $inventoryCache = [];

    public function __construct(
        private readonly SkillInventory $inventory = new SkillInventory(),
        private readonly ?Closure $inventoryResolver = null,
    ) {}

    /**
     * @param list<string> $targets
     *
     * @return list<SkillManagedMetadata>
     */
    public function inventory(string $cwd, array $targets): array
    {
        $cacheKey = $this->cacheKey($cwd, $targets);

        if (! array_key_exists($cacheKey, $this->inventoryCache)) {
            $this->inventoryCache[$cacheKey] = $this->resolveInventory($cwd, $targets);
        }

        return $this->inventoryCache[$cacheKey];
    }

    /**
     * @param list<SkillManagedMetadata> $items
     * @param list<string> $names
     *
     * @return list<SkillManagedMetadata>
     */
    public function selectByName(array $items, array $names): array
    {
        if ($names === [] || in_array('*', $names, true)) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static fn(SkillManagedMetadata $metadata): bool => in_array($metadata->name(), $names, true),
        ));
    }

    /**
     * @param list<string> $targets
     */
    private function cacheKey(string $cwd, array $targets): string
    {
        return $cwd . "\0" . implode("\0", $targets);
    }

    /**
     * @param list<string> $targets
     *
     * @return list<SkillManagedMetadata>
     */
    private function resolveInventory(string $cwd, array $targets): array
    {
        if ($this->inventoryResolver instanceof Closure) {
            $items = ($this->inventoryResolver)($cwd, $targets);

            if (is_array($items)) {
                return array_values(array_filter(
                    $items,
                    static fn(mixed $item): bool => $item instanceof SkillManagedMetadata,
                ));
            }

            return [];
        }

        return $this->inventory->list($cwd, $targets);
    }
}
