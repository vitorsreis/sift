<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Skills\SkillManagedMetadata;
use Sift\Skills\SkillService;
use Sift\Skills\Targets\InstructionTargetRegistry;

final readonly class SkillsListCommand implements CommandHandler
{
    public function __construct(
        private SkillService $skillService = new SkillService(),
        private InstructionTargetRegistry $targetRegistry = new InstructionTargetRegistry(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $targets = $this->targets($route);
        $items = $this->filterBySkill($this->skillService->inventory($cwd, $targets), $route);

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
        $names = $this->selectedNames($route);

        if ($names === [] || in_array('*', $names, true)) {
            return $items;
        }

        return $this->skillService->selectByName($items, $names);
    }

    /**
     * @return list<string>
     */
    private function selectedNames(CommandRoute $route): array
    {
        $value = $route->options()['skill'] ?? null;
        $values = is_array($value) ? $value : [$value];
        $names = [];

        foreach ($values as $item) {
            if (! is_string($item)) {
                continue;
            }

            foreach (explode(',', $item) as $name) {
                $name = trim($name);

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }
}
