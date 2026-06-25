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
        $global = $this->optionBool($route, 'global');
        $targets = $this->targets($route, $global);
        $items = $this->filterBySkill($this->skillService->inventory($cwd, $targets, $global), $route);

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
                'global' => $global,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function targets(CommandRoute $route, bool $global): array
    {
        $agents = $this->stringOptionValues($route, 'agent');

        if ($agents === []) {
            return $this->targetRegistry->writeCapableNames($global);
        }

        $targets = [];

        foreach ($agents as $target) {
            $target = trim($target);

            if ($target === '' || in_array($target, ['*', 'all'], true)) {
                return $this->targetRegistry->writeCapableNames($global);
            }

            $targets[] = $target;
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
        $values = $this->stringOptionValues($route, 'skill');
        $names = $values;

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function stringOptionValues(CommandRoute $route, string $name): array
    {
        $value = $route->options()[$name] ?? null;
        $values = is_array($value) ? $value : [$value];
        $strings = [];

        foreach ($values as $item) {
            if (! is_string($item)) {
                continue;
            }

            foreach (explode(',', $item) as $string) {
                if (trim($string) !== '') {
                    $strings[] = trim($string);
                }
            }
        }

        return $strings;
    }

    private function optionBool(CommandRoute $route, string $name): bool
    {
        return ($route->options()[$name] ?? false) === true;
    }
}
