<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Skills\SkillInventory;
use Sift\Skills\SkillManagedMetadata;
use Sift\Skills\Targets\InstructionTargetRegistry;

final readonly class SkillsListCommand implements CommandHandler
{
    public function __construct(
        private SkillInventory $inventory = new SkillInventory(),
        private InstructionTargetRegistry $targetRegistry = new InstructionTargetRegistry(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $targets = $this->targets($route);
        $items = $this->filterBySkill($this->inventory->list($cwd, $targets), $route);

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'total' => count($items),
            ],
            'items' => array_map(static fn(SkillManagedMetadata $metadata): array => $metadata->toItem(), $items),
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills list',
                'targets' => $targets,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function targets(CommandRoute $route): array
    {
        $agent = $route->options()['agent'] ?? null;

        if (! is_string($agent) || trim($agent) === '' || in_array(trim($agent), ['*', 'all'], true)) {
            return $this->targetRegistry->writeCapableNames();
        }

        $targets = [];

        foreach (explode(',', $agent) as $target) {
            $target = trim($target);

            if ($target !== '') {
                $targets[] = $target;
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * @param list<SkillManagedMetadata> $items
     *
     * @return list<SkillManagedMetadata>
     */
    private function filterBySkill(array $items, CommandRoute $route): array
    {
        $selector = $route->options()['skill'] ?? null;

        if (! is_string($selector) || trim($selector) === '' || trim($selector) === '*') {
            return $items;
        }

        $names = array_map(trim(...), explode(',', $selector));

        return array_values(array_filter(
            $items,
            static fn(SkillManagedMetadata $metadata): bool => in_array($metadata->name(), $names, true),
        ));
    }
}
